<?php

namespace App\Services\Finance;

use App\Models\AuditLog;
use App\Models\PayoutAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayoutAccountService
{
    public function createForOwner(
        User $owner,
        string $bankCode,
        string $bankName,
        string $accountNumber,
        string $accountHolderName,
    ): PayoutAccount {
        abort_unless($owner->isOwner() && $owner->status === 'active' && $owner->email_verified_at !== null, 403);
        $this->assertOwnerMfaFresh($owner);

        $bankCode = strtoupper(trim($bankCode));
        $bankName = trim($bankName);
        $accountNumber = preg_replace('/\s+/', '', $accountNumber) ?? '';
        $accountHolderName = trim($accountHolderName);

        if (
            ! preg_match('/^[A-Z0-9_-]{2,30}$/', $bankCode)
            || $bankName === ''
            || mb_strlen($bankName) > 100
            || ! preg_match('/^[0-9]{6,30}$/', $accountNumber)
            || $accountHolderName === ''
            || mb_strlen($accountHolderName) > 140
        ) {
            throw ValidationException::withMessages([
                'payoutAccount' => 'Data rekening tidak valid. Periksa kode bank, nomor rekening, dan nama pemilik rekening.',
            ]);
        }

        return DB::transaction(function () use ($owner, $bankCode, $bankName, $accountNumber, $accountHolderName): PayoutAccount {
            $account = $owner->payoutAccounts()->create([
                'bank_code' => $bankCode,
                'bank_name' => $bankName,
                'account_number_encrypted' => $accountNumber,
                'account_number_last4' => substr($accountNumber, -4),
                'account_holder_name' => $accountHolderName,
                'verification_status' => PayoutAccount::STATUS_PENDING,
                'verified_by' => null,
                'verified_at' => null,
            ]);

            AuditLog::query()->create([
                'actor_user_id' => $owner->getKey(),
                'actor_role' => $owner->role,
                'action' => 'payout_account.created',
                'auditable_type' => PayoutAccount::class,
                'auditable_id' => $account->getKey(),
                'reason' => 'Owner submitted a payout account for Super Admin verification.',
                'before_state_json' => null,
                'after_state_json' => [
                    'bank_code' => $account->bank_code,
                    'bank_name' => $account->bank_name,
                    'account_number_last4' => $account->account_number_last4,
                    'verification_status' => $account->verification_status,
                ],
                'ip_hash' => request()->ip() ? hash('sha256', (string) request()->ip()) : null,
                'created_at' => now('UTC'),
            ]);

            return $account;
        }, attempts: 3);
    }

    public function review(
        PayoutAccount $account,
        User $admin,
        string $decision,
        string $reason,
    ): PayoutAccount {
        abort_unless($admin->isSuperAdmin() && $admin->status === 'active', 403);

        $decision = strtolower(trim($decision));
        $reason = trim($reason);

        if (! in_array($decision, ['verify', 'reject'], true)) {
            throw ValidationException::withMessages([
                'decision' => 'Keputusan rekening tidak valid.',
            ]);
        }

        if ($reason === '' || mb_strlen($reason) > 1000) {
            throw ValidationException::withMessages([
                'reason' => 'Alasan review wajib diisi dan maksimal 1.000 karakter.',
            ]);
        }

        return DB::transaction(function () use ($account, $admin, $decision, $reason): PayoutAccount {
            $locked = PayoutAccount::query()->whereKey($account->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->verification_status !== PayoutAccount::STATUS_PENDING) {
                throw ValidationException::withMessages([
                    'account' => 'Hanya rekening dengan status pending yang dapat direview.',
                ]);
            }

            $before = $locked->verification_status;
            $next = $decision === 'verify'
                ? PayoutAccount::STATUS_VERIFIED
                : PayoutAccount::STATUS_REJECTED;

            $locked->update([
                'verification_status' => $next,
                'verified_by' => $admin->getKey(),
                'verified_at' => $decision === 'verify' ? now('UTC') : null,
            ]);

            AuditLog::query()->create([
                'actor_user_id' => $admin->getKey(),
                'actor_role' => $admin->role,
                'action' => 'payout_account.reviewed',
                'auditable_type' => PayoutAccount::class,
                'auditable_id' => $locked->getKey(),
                'reason' => $reason,
                'before_state_json' => ['verification_status' => $before],
                'after_state_json' => [
                    'verification_status' => $next,
                    'bank_code' => $locked->bank_code,
                    'account_number_last4' => $locked->account_number_last4,
                ],
                'ip_hash' => request()->ip() ? hash('sha256', (string) request()->ip()) : null,
                'created_at' => now('UTC'),
            ]);

            return $locked->refresh();
        }, attempts: 3);
    }

    private function assertOwnerMfaFresh(User $owner): void
    {
        if (! app()->environment('production')) {
            return;
        }

        $verifiedId = session('owner_mfa_verified_user_id');
        $verifiedAt = session('owner_mfa_verified_at');
        $now = now()->timestamp;

        if (
            ! $owner->mfa_secret
            || ! $owner->mfa_enabled_at
            || ! is_scalar($verifiedId)
            || (string) $verifiedId !== (string) $owner->getKey()
            || ! is_numeric($verifiedAt)
            || (int) $verifiedAt < $now - 600
            || (int) $verifiedAt > $now
        ) {
            abort(403, 'Verifikasi MFA Owner diperlukan untuk mengubah rekening payout.');
        }
    }
}
