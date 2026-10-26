<?php

namespace App\Services\Finance;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Payout;
use App\Models\PayoutAccount;
use App\Models\Refund;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PayoutService
{
    public function __construct(private readonly LedgerService $ledger)
    {
    }

    /**
     * Create payout aggregates after the completion window closes and no refund obligation
     * is open. Accounts not yet verified leave the payout held.
     *
     * @return array{created:int,eligible:int,held:int,skipped:int}
     */
    public function holdForConfirmedBooking(Booking $booking): Payout
    {
        $lockedBooking = Booking::query()->whereKey($booking->getKey())->lockForUpdate()->firstOrFail();

        if (! in_array($lockedBooking->status, [
            Booking::STATUS_CONFIRMED,
            Booking::STATUS_IN_PROGRESS,
            Booking::STATUS_AWAITING_COMPLETION,
            Booking::STATUS_COMPLETED,
        ], true)) {
            throw ValidationException::withMessages([
                'payout' => 'Payout hanya dapat disiapkan setelah booking terkonfirmasi.',
            ]);
        }

        $existing = Payout::query()->where('booking_id', $lockedBooking->getKey())->lockForUpdate()->first();

        if ($existing) {
            return $existing;
        }

        $gross = max(0, (int) $lockedBooking->snapshot_base_amount - (int) $lockedBooking->snapshot_owner_discount_amount);
        $fee = max(0, (int) $lockedBooking->snapshot_gateway_fee_amount);
        $net = max(0, $gross - $fee);

        if ($net !== (int) $lockedBooking->snapshot_net_owner_entitlement) {
            throw ValidationException::withMessages([
                'payout' => 'Snapshot hak bersih Owner tidak konsisten; payout ditahan untuk rekonsiliasi.',
            ]);
        }

        $payout = Payout::query()->create([
            'reference_code' => 'PAY-' . now('UTC')->format('Ymd') . '-' . Str::upper(Str::random(8)),
            'owner_id' => $lockedBooking->business->owner_id,
            'booking_id' => $lockedBooking->getKey(),
            'payout_account_id' => null,
            'gross_amount' => $gross,
            'gateway_fee_deducted' => $fee,
            'net_amount' => $net,
            'status' => $net === 0 ? Payout::STATUS_CANCELLED : Payout::STATUS_HELD,
            'transfer_reference' => null,
            'processed_by' => null,
            'eligible_at' => $lockedBooking->booking_end->copy()->utc()->addHours(24),
            'paid_at' => null,
        ]);

        AuditLog::query()->create([
            'actor_user_id' => null,
            'actor_role' => 'system',
            'action' => 'payout.held_created',
            'auditable_type' => Payout::class,
            'auditable_id' => $payout->getKey(),
            'reason' => $net === 0
                ? 'Booking entitlement is zero; no payout is due.'
                : 'A held payout aggregate was created at booking confirmation and cannot be released before the 24-hour dispute window closes.',
            'before_state_json' => null,
            'after_state_json' => [
                'status' => $payout->status,
                'booking_id' => $lockedBooking->getKey(),
                'gross_amount' => $gross,
                'gateway_fee_deducted' => $fee,
                'net_amount' => $net,
                'eligible_at' => $payout->eligible_at?->toISOString(),
            ],
            'ip_hash' => null,
            'created_at' => now('UTC'),
        ]);

        return $payout;
    }

    public function prepareEligiblePayouts(int $limit = 100): array
    {
        $bookingIds = Booking::query()
            ->where('status', Booking::STATUS_COMPLETED)
            ->whereNotNull('completion_window_ends_at')
            ->where('completion_window_ends_at', '<=', now('UTC'))
            ->whereDoesntHave('payout')
            ->whereDoesntHave('refunds', fn ($query) => $query->whereIn('status', Refund::OPEN_STATUSES))
            ->orderBy('completion_window_ends_at')
            ->limit(max(1, min($limit, 500)))
            ->pluck('id');

        $result = ['created' => 0, 'eligible' => 0, 'held' => 0, 'cancelled' => 0, 'skipped' => 0];

        foreach ($bookingIds as $bookingId) {
            $initial = Booking::query()->with('venue')->find($bookingId);

            if (! $initial) {
                $result['skipped']++;

                continue;
            }

            $outcome = DB::transaction(function () use ($initial): string {
                Venue::query()->whereKey($initial->venue_id)->lockForUpdate()->firstOrFail();
                $booking = Booking::query()->with('business')->whereKey($initial->getKey())->lockForUpdate()->firstOrFail();

                if (
                    $booking->status !== Booking::STATUS_COMPLETED
                    || $booking->completion_window_ends_at === null
                    || $booking->completion_window_ends_at->isFuture()
                    || Payout::query()->where('booking_id', $booking->getKey())->exists()
                    || Refund::query()->where('booking_id', $booking->getKey())->whereIn('status', Refund::OPEN_STATUSES)->exists()
                ) {
                    return 'skipped';
                }

                $gross = max(0, (int) $booking->snapshot_base_amount - (int) $booking->snapshot_owner_discount_amount);
                $fee = max(0, (int) $booking->snapshot_gateway_fee_amount);
                $net = max(0, $gross - $fee);
                $expectedNet = (int) $booking->snapshot_net_owner_entitlement;

                $account = PayoutAccount::query()
                    ->where('owner_id', $booking->business->owner_id)
                    ->where('verification_status', PayoutAccount::STATUS_VERIFIED)
                    ->orderByDesc('verified_at')
                    ->orderByDesc('id')
                    ->first();

                $status = $net === 0
                    ? Payout::STATUS_CANCELLED
                    : (($expectedNet === $net && $account) ? Payout::STATUS_ELIGIBLE : Payout::STATUS_HELD);

                $payout = Payout::query()->create([
                    'reference_code' => 'PAY-' . now('UTC')->format('Ymd') . '-' . Str::upper(Str::random(8)),
                    'owner_id' => $booking->business->owner_id,
                    'booking_id' => $booking->getKey(),
                    'payout_account_id' => $account?->getKey(),
                    'gross_amount' => $gross,
                    'gateway_fee_deducted' => $fee,
                    'net_amount' => $net,
                    'status' => $status,
                    'transfer_reference' => null,
                    'processed_by' => null,
                    'eligible_at' => $booking->completion_window_ends_at,
                    'paid_at' => null,
                ]);

                AuditLog::query()->create([
                    'actor_user_id' => null,
                    'actor_role' => 'system',
                    'action' => 'payout.created',
                    'auditable_type' => Payout::class,
                    'auditable_id' => $payout->getKey(),
                    'reason' => $net === 0
                        ? 'No payout is due because net owner entitlement is zero.'
                        : ($status === Payout::STATUS_ELIGIBLE
                            ? 'Completion window closed and verified payout account is available.'
                            : 'Payout is held until account verification or financial snapshot reconciliation.'),
                    'before_state_json' => null,
                    'after_state_json' => [
                        'status' => $status,
                        'gross_amount' => $gross,
                        'gateway_fee_deducted' => $fee,
                        'net_amount' => $net,
                    ],
                    'ip_hash' => null,
                    'created_at' => now('UTC'),
                ]);

                return $status;
            }, attempts: 3);

            if ($outcome === 'skipped') {
                $result['skipped']++;
            } else {
                $result['created']++;
                if ($outcome === Payout::STATUS_ELIGIBLE) {
                    $result['eligible']++;
                } elseif ($outcome === Payout::STATUS_CANCELLED) {
                    $result['cancelled']++;
                } else {
                    $result['held']++;
                }
            }
        }

        $this->promoteHeldPayouts(max(1, min($limit, 500)));

        return $result;
    }

    public function reviewPayoutAccount(PayoutAccount $account, User $admin, string $decision, string $reason): PayoutAccount
    {
        abort_unless($admin->isSuperAdmin() && $admin->status === 'active', 403);

        $reason = trim($reason);

        if (
            ! in_array($decision, [PayoutAccount::STATUS_VERIFIED, PayoutAccount::STATUS_REJECTED], true)
            || $reason === ''
            || mb_strlen($reason) > 2000
        ) {
            throw ValidationException::withMessages([
                'accountReview' => 'Keputusan verifikasi tidak valid atau alasan wajib diisi.',
            ]);
        }

        return DB::transaction(function () use ($account, $admin, $decision, $reason): PayoutAccount {
            $locked = PayoutAccount::query()->whereKey($account->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->verification_status !== PayoutAccount::STATUS_PENDING) {
                throw ValidationException::withMessages([
                    'accountReview' => 'Hanya rekening yang menunggu verifikasi dapat diproses.',
                ]);
            }

            $before = $locked->verification_status;
            $locked->update([
                'verification_status' => $decision,
                'verified_by' => $admin->getKey(),
                'verified_at' => $decision === PayoutAccount::STATUS_VERIFIED ? now('UTC') : null,
            ]);

            AuditLog::query()->create([
                'actor_user_id' => $admin->getKey(),
                'actor_role' => $admin->role,
                'action' => $decision === PayoutAccount::STATUS_VERIFIED
                    ? 'payout_account.verified'
                    : 'payout_account.rejected',
                'auditable_type' => PayoutAccount::class,
                'auditable_id' => $locked->getKey(),
                'reason' => $reason,
                'before_state_json' => [
                    'verification_status' => $before,
                ],
                'after_state_json' => [
                    'verification_status' => $decision,
                    'owner_id' => $locked->owner_id,
                    'bank_code' => $locked->bank_code,
                    'account_number_last4' => $locked->account_number_last4,
                    'account_holder_name' => $locked->account_holder_name,
                ],
                'ip_hash' => request()->ip() ? hash('sha256', (string) request()->ip()) : null,
                'created_at' => now('UTC'),
            ]);

            return $locked->refresh();
        }, attempts: 3);
    }

    public function markPaid(Payout $payout, User $admin, string $transferReference): Payout
    {
        abort_unless($admin->isSuperAdmin() && $admin->status === 'active', 403);

        $transferReference = trim($transferReference);

        if ($transferReference === '' || mb_strlen($transferReference) > 120) {
            throw ValidationException::withMessages([
                'transferReference' => 'Referensi transfer wajib diisi dan maksimal 120 karakter.',
            ]);
        }

        $initial = Payout::query()->with('booking.venue')->whereKey($payout->getKey())->firstOrFail();
        $venueId = $initial->booking->venue_id;

        return DB::transaction(function () use ($payout, $admin, $transferReference, $venueId): Payout {
            Venue::query()->whereKey($venueId)->lockForUpdate()->firstOrFail();
            $locked = Payout::query()->with('booking')->whereKey($payout->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status === Payout::STATUS_PAID) {
                return $locked;
            }

            if (! in_array($locked->status, [Payout::STATUS_ELIGIBLE, Payout::STATUS_PROCESSING], true)) {
                throw ValidationException::withMessages([
                    'payout' => 'Payout belum eligible atau sedang ditahan.',
                ]);
            }

            $account = $locked->payoutAccount()->lockForUpdate()->first();

            if (! $account || $account->verification_status !== PayoutAccount::STATUS_VERIFIED || (int) $account->owner_id !== (int) $locked->owner_id) {
                throw ValidationException::withMessages([
                    'payoutAccount' => 'Payout memerlukan rekening Owner yang sudah diverifikasi.',
                ]);
            }

            if (Refund::query()->where('booking_id', $locked->booking_id)->whereIn('status', Refund::OPEN_STATUSES)->exists()) {
                throw ValidationException::withMessages([
                    'payout' => 'Payout tidak dapat diselesaikan selama kewajiban refund masih terbuka.',
                ]);
            }

            if ($locked->net_amount > 0) {
                $this->ledger->postBalancedGroup([
                    [
                        'account_code' => 'owner_payable',
                        'entry_type' => 'debit',
                        'amount' => (int) $locked->net_amount,
                        'reference_type' => 'payout',
                        'reference_id' => $locked->getKey(),
                        'description' => 'Settle owner payable after verified manual payout.',
                    ],
                    [
                        'account_code' => 'customer_cash',
                        'entry_type' => 'credit',
                        'amount' => (int) $locked->net_amount,
                        'reference_type' => 'payout',
                        'reference_id' => $locked->getKey(),
                        'description' => 'Cash transfer recorded for owner payout.',
                    ],
                ]);
            }

            $before = $locked->status;
            $locked->update([
                'status' => Payout::STATUS_PAID,
                'transfer_reference' => $transferReference,
                'processed_by' => $admin->getKey(),
                'paid_at' => now('UTC'),
            ]);

            AuditLog::query()->create([
                'actor_user_id' => $admin->getKey(),
                'actor_role' => $admin->role,
                'action' => 'payout.marked_paid',
                'auditable_type' => Payout::class,
                'auditable_id' => $locked->getKey(),
                'reason' => 'Super Admin recorded verified manual transfer reference.',
                'before_state_json' => ['status' => $before],
                'after_state_json' => [
                    'status' => Payout::STATUS_PAID,
                    'transfer_reference' => $transferReference,
                    'net_amount' => $locked->net_amount,
                ],
                'ip_hash' => request()->ip() ? hash('sha256', (string) request()->ip()) : null,
                'created_at' => now('UTC'),
            ]);

            return $locked->refresh();
        }, attempts: 3);
    }

    private function promoteHeldPayouts(int $limit): void
    {
        $payoutIds = Payout::query()
            ->where('status', Payout::STATUS_HELD)
            ->whereNotNull('eligible_at')
            ->where('eligible_at', '<=', now('UTC'))
            ->orderBy('eligible_at')
            ->limit($limit)
            ->pluck('id');

        foreach ($payoutIds as $payoutId) {
            $initial = Payout::query()->with('booking')->find($payoutId);

            if (! $initial || ! $initial->booking) {
                continue;
            }

            DB::transaction(function () use ($initial): void {
                Venue::query()->whereKey($initial->booking->venue_id)->lockForUpdate()->firstOrFail();
                $payout = Payout::query()->whereKey($initial->getKey())->lockForUpdate()->firstOrFail();
                $booking = Booking::query()->whereKey($payout->booking_id)->lockForUpdate()->firstOrFail();

                if (
                    $payout->status !== Payout::STATUS_HELD
                    || $booking->status !== Booking::STATUS_COMPLETED
                    || $payout->eligible_at === null
                    || $payout->eligible_at->isFuture()
                    || (int) $payout->net_amount !== max(0, (int) $payout->gross_amount - (int) $payout->gateway_fee_deducted)
                    || Refund::query()->where('booking_id', $booking->getKey())->whereIn('status', Refund::OPEN_STATUSES)->exists()
                ) {
                    return;
                }

                $account = PayoutAccount::query()
                    ->where('owner_id', $payout->owner_id)
                    ->where('verification_status', PayoutAccount::STATUS_VERIFIED)
                    ->orderByDesc('verified_at')
                    ->orderByDesc('id')
                    ->lockForUpdate()
                    ->first();

                if (! $account) {
                    return;
                }

                $payout->update([
                    'payout_account_id' => $account->getKey(),
                    'status' => Payout::STATUS_ELIGIBLE,
                ]);

                AuditLog::query()->create([
                    'actor_user_id' => null,
                    'actor_role' => 'system',
                    'action' => 'payout.became_eligible',
                    'auditable_type' => Payout::class,
                    'auditable_id' => $payout->getKey(),
                    'reason' => 'A verified payout account became available for an otherwise eligible payout.',
                    'before_state_json' => ['status' => Payout::STATUS_HELD],
                    'after_state_json' => [
                        'status' => Payout::STATUS_ELIGIBLE,
                        'payout_account_id' => $account->getKey(),
                    ],
                    'ip_hash' => null,
                    'created_at' => now('UTC'),
                ]);
            }, attempts: 3);
        }
    }
}
