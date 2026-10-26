<?php

namespace App\Services\Booking;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Dispute;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\PayoutAccount;
use App\Models\Refund;
use App\Models\User;
use App\Models\Venue;
use App\Services\Payments\RefundService;
use App\Services\Finance\LedgerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DisputeResolutionService
{
    public function __construct(
        private readonly RefundService $refunds,
        private readonly LedgerService $ledger,
    )
    {
    }

    /**
     * @return array{dispute:Dispute,refund:?Refund,payout:Payout}
     */
    public function resolve(
        User $admin,
        Dispute $dispute,
        string $decision,
        int $refundAmount,
        int $payoutAmount,
        string $resolutionNotes,
    ): array {
        abort_unless($admin->isSuperAdmin() && $admin->status === 'active', 403);

        $resolutionNotes = trim($resolutionNotes);

        if (
            ! in_array($decision, [
                Dispute::STATUS_RESOLVED_TO_CUSTOMER,
                Dispute::STATUS_RESOLVED_TO_OWNER,
                Dispute::STATUS_RESOLVED_SPLIT,
            ], true)
            || $resolutionNotes === ''
            || mb_strlen($resolutionNotes) < 20
            || mb_strlen($resolutionNotes) > 5000
            || $refundAmount < 0
            || $payoutAmount < 0
        ) {
            throw ValidationException::withMessages([
                'resolution' => 'Keputusan, nominal, atau alasan resolusi tidak valid.',
            ]);
        }

        $initial = Dispute::query()->with('booking')->whereKey($dispute->getKey())->firstOrFail();
        $venueId = $initial->booking->venue_id;

        $result = DB::transaction(function () use (
            $admin,
            $dispute,
            $decision,
            $refundAmount,
            $payoutAmount,
            $resolutionNotes,
            $venueId,
        ): array {
            Venue::query()->whereKey($venueId)->lockForUpdate()->firstOrFail();

            $lockedDispute = Dispute::query()
                ->whereKey($dispute->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $booking = Booking::query()
                ->with('business')
                ->whereKey($lockedDispute->booking_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($lockedDispute->status, Dispute::OPEN_STATUSES, true) || $booking->status !== Booking::STATUS_DISPUTED) {
                throw ValidationException::withMessages([
                    'dispute' => 'Sengketa sudah diproses atau booking tidak lagi berstatus disputed.',
                ]);
            }

            $actualPaid = (int) $booking->snapshot_actual_paid_amount;
            $normalOwnerEntitlement = (int) $booking->snapshot_net_owner_entitlement;

            if ($decision === Dispute::STATUS_RESOLVED_TO_CUSTOMER) {
                $refundAmount = $actualPaid;
                $payoutAmount = 0;
            } elseif ($decision === Dispute::STATUS_RESOLVED_TO_OWNER) {
                $refundAmount = 0;
                $payoutAmount = $normalOwnerEntitlement;
            } elseif (
                $refundAmount > $actualPaid
                || $payoutAmount > $normalOwnerEntitlement
                || ($refundAmount === 0 && $payoutAmount === 0 && ($actualPaid > 0 || $normalOwnerEntitlement > 0))
            ) {
                throw ValidationException::withMessages([
                    'resolution' => 'Nominal keputusan parsial tidak boleh melebihi snapshot refund atau hak bersih Owner.',
                ]);
            }

            $payment = null;
            $refund = null;

            if ($refundAmount > 0) {
                $payment = Payment::query()
                    ->where('payable_type', Payment::TYPE_BOOKING)
                    ->where('payable_id', $booking->getKey())
                    ->where('status', Payment::STATUS_PAID)
                    ->lockForUpdate()
                    ->first();

                if (! $payment || (int) $payment->amount !== $actualPaid) {
                    throw ValidationException::withMessages([
                        'payment' => 'Pembayaran tidak dapat diverifikasi untuk membuat refund hasil sengketa.',
                    ]);
                }

                $refund = $this->refunds->createRequestedForBooking(
                    $booking,
                    $payment,
                    $refundAmount,
                    'dispute_resolution',
                    'Resolusi sengketa ' . $lockedDispute->reference_code . ': ' . $resolutionNotes,
                    'refund:dispute:' . $lockedDispute->getKey(),
                    $admin->getKey(),
                );
            }

            $beforeDisputeStatus = $lockedDispute->status;
            $ownerRemainingAfterRefund = max(0, $normalOwnerEntitlement - min($refundAmount, $normalOwnerEntitlement));
            $allocationEntries = [];

            if ($ownerRemainingAfterRefund > $payoutAmount) {
                $unawardedOwnerBalance = $ownerRemainingAfterRefund - $payoutAmount;
                $allocationEntries[] = [
                    'account_code' => 'owner_payable',
                    'entry_type' => 'debit',
                    'amount' => $unawardedOwnerBalance,
                    'reference_type' => 'dispute',
                    'reference_id' => $lockedDispute->getKey(),
                    'description' => 'Remove Owner payable not awarded by the final dispute resolution.',
                ];
                $allocationEntries[] = [
                    'account_code' => 'escrow_liability',
                    'entry_type' => 'credit',
                    'amount' => $unawardedOwnerBalance,
                    'reference_type' => 'dispute',
                    'reference_id' => $lockedDispute->getKey(),
                    'description' => 'Hold unallocated dispute residual pending a separately approved accounting disposition.',
                ];
            } elseif ($payoutAmount > $ownerRemainingAfterRefund) {
                $platformFundedAward = $payoutAmount - $ownerRemainingAfterRefund;
                $allocationEntries[] = [
                    'account_code' => 'platform_subsidy_expense',
                    'entry_type' => 'debit',
                    'amount' => $platformFundedAward,
                    'reference_type' => 'dispute',
                    'reference_id' => $lockedDispute->getKey(),
                    'description' => 'Platform funds the additional Owner award authorized by dispute resolution.',
                ];
                $allocationEntries[] = [
                    'account_code' => 'owner_payable',
                    'entry_type' => 'credit',
                    'amount' => $platformFundedAward,
                    'reference_type' => 'dispute',
                    'reference_id' => $lockedDispute->getKey(),
                    'description' => 'Recognize additional Owner payable awarded by Super Admin.',
                ];
            }

            if ($allocationEntries !== []) {
                $this->ledger->postBalancedGroup($allocationEntries);
            }

            $account = PayoutAccount::query()
                ->where('owner_id', $booking->business->owner_id)
                ->where('verification_status', PayoutAccount::STATUS_VERIFIED)
                ->orderByDesc('verified_at')
                ->orderByDesc('id')
                ->first();

            $payout = Payout::query()->where('booking_id', $booking->getKey())->lockForUpdate()->first();

            if ($payout?->status === Payout::STATUS_PAID) {
                throw ValidationException::withMessages([
                    'payout' => 'Payout sudah dibayarkan; resolusi finansial memerlukan rekonsiliasi Super Admin.',
                ]);
            }

            $beforePayoutStatus = $payout?->status;
            $payoutStatus = $payoutAmount === 0
                ? Payout::STATUS_CANCELLED
                : ($refundAmount > 0
                    ? Payout::STATUS_ON_HOLD
                    : ($account ? Payout::STATUS_ELIGIBLE : Payout::STATUS_HELD));

            if (! $payout) {
                $payout = Payout::query()->create([
                    'reference_code' => 'PAY-' . now('UTC')->format('Ymd') . '-' . Str::upper(Str::random(8)),
                    'owner_id' => $booking->business->owner_id,
                    'booking_id' => $booking->getKey(),
                    'payout_account_id' => $account?->getKey(),
                    'gross_amount' => max(0, (int) $booking->snapshot_base_amount - (int) $booking->snapshot_owner_discount_amount),
                    'gateway_fee_deducted' => max(0, (int) $booking->snapshot_gateway_fee_amount),
                    'net_amount' => $payoutAmount,
                    'status' => $payoutStatus,
                    'transfer_reference' => null,
                    'processed_by' => null,
                    'eligible_at' => now('UTC'),
                    'paid_at' => null,
                ]);
            } else {
                $payout->update([
                    'payout_account_id' => $account?->getKey(),
                    'net_amount' => $payoutAmount,
                    'status' => $payoutStatus,
                    'eligible_at' => now('UTC'),
                ]);
            }

            $beforeBookingStatus = $booking->status;
            $booking->update([
                'status' => $decision === Dispute::STATUS_RESOLVED_TO_CUSTOMER
                    ? Booking::STATUS_CANCELLED_BY_ADMIN
                    : Booking::STATUS_COMPLETED,
                'completion_window_ends_at' => now('UTC'),
                'slot_released_at' => $decision === Dispute::STATUS_RESOLVED_TO_CUSTOMER ? now('UTC') : $booking->slot_released_at,
            ]);

            $lockedDispute->update([
                'status' => $decision,
                'resolution_notes' => $resolutionNotes,
                'refund_amount_awarded' => $refundAmount,
                'payout_amount_awarded' => $payoutAmount,
                'resolved_by' => $admin->getKey(),
                'resolved_at' => now('UTC'),
            ]);

            AuditLog::query()->create([
                'actor_user_id' => $admin->getKey(),
                'actor_role' => $admin->role,
                'action' => 'booking.dispute_resolved',
                'auditable_type' => Dispute::class,
                'auditable_id' => $lockedDispute->getKey(),
                'reason' => $resolutionNotes,
                'before_state_json' => [
                    'dispute_status' => $beforeDisputeStatus,
                    'booking_status' => $beforeBookingStatus,
                    'payout_status' => $beforePayoutStatus,
                ],
                'after_state_json' => [
                    'dispute_status' => $decision,
                    'booking_status' => $booking->status,
                    'refund_amount_awarded' => $refundAmount,
                    'payout_amount_awarded' => $payoutAmount,
                    'payout_status' => $payoutStatus,
                    'refund_id' => $refund?->getKey(),
                ],
                'ip_hash' => request()->ip() ? hash('sha256', (string) request()->ip()) : null,
                'created_at' => now('UTC'),
            ]);

            return [
                'dispute' => $lockedDispute->refresh(),
                'refund' => $refund,
                'payout' => $payout->refresh(),
            ];
        }, attempts: 3);

        if ($result['refund'] instanceof Refund) {
            $result['refund'] = $this->refunds->submit($result['refund']);
        }

        return $result;
    }
}
