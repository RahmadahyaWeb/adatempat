<?php

namespace App\Services\Disputes;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Dispute;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\PayoutAccount;
use App\Models\Refund;
use App\Models\User;
use App\Models\Venue;
use App\Services\Finance\PayoutService;
use App\Services\Payments\RefundService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DisputeResolutionService
{
    public function __construct(
        private readonly RefundService $refunds,
        private readonly PayoutService $payouts,
    ) {
    }

    public function resolve(
        Dispute $dispute,
        User $admin,
        string $resolutionType,
        int $customerRefundAmount,
        int $ownerPayoutAmount,
        string $notes,
    ): Dispute {
        abort_unless($admin->isSuperAdmin() && $admin->status === 'active', 403);

        $notes = trim($notes);

        if ($notes === '' || mb_strlen($notes) > 5000) {
            throw ValidationException::withMessages([
                'notes' => 'Catatan keputusan wajib diisi dan maksimal 5.000 karakter.',
            ]);
        }

        $initial = Dispute::query()->with('booking')->whereKey($dispute->getKey())->firstOrFail();
        $bookingId = $initial->booking_id;
        $venueId = $initial->booking->venue_id;

        $result = DB::transaction(function () use (
            $dispute,
            $admin,
            $resolutionType,
            $customerRefundAmount,
            $ownerPayoutAmount,
            $notes,
            $bookingId,
            $venueId,
        ): array {
            Venue::query()->whereKey($venueId)->lockForUpdate()->firstOrFail();
            $payout = Payout::query()->where('booking_id', $bookingId)->lockForUpdate()->first();
            $booking = Booking::query()->whereKey($bookingId)->lockForUpdate()->firstOrFail();
            $locked = Dispute::query()->whereKey($dispute->getKey())->lockForUpdate()->firstOrFail();

            if (in_array($locked->status, [Dispute::STATUS_RESOLVED, Dispute::STATUS_REJECTED], true)) {
                throw ValidationException::withMessages([
                    'dispute' => 'Sengketa ini sudah memiliki keputusan final.',
                ]);
            }

            $actualPaid = (int) $booking->snapshot_actual_paid_amount;
            $gross = max(0, (int) $booking->snapshot_base_amount - (int) $booking->snapshot_owner_discount_amount);
            $netOwnerEntitlement = (int) $booking->snapshot_net_owner_entitlement;

            if ($resolutionType === Dispute::RESOLUTION_FULL_OWNER_PAYOUT) {
                $customerRefundAmount = 0;
                $ownerPayoutAmount = $netOwnerEntitlement;
            } elseif ($resolutionType === Dispute::RESOLUTION_FULL_CUSTOMER_REFUND) {
                $customerRefundAmount = $actualPaid;
                $ownerPayoutAmount = 0;
            } elseif ($resolutionType !== Dispute::RESOLUTION_PARTIAL_SPLIT) {
                throw ValidationException::withMessages([
                    'resolutionType' => 'Jenis keputusan sengketa tidak valid.',
                ]);
            }

            if (
                $customerRefundAmount < 0
                || $customerRefundAmount > $actualPaid
                || $ownerPayoutAmount < 0
                || $ownerPayoutAmount > $netOwnerEntitlement
                || $customerRefundAmount + $ownerPayoutAmount > $gross
                || ($resolutionType === Dispute::RESOLUTION_PARTIAL_SPLIT && $customerRefundAmount === 0 && $ownerPayoutAmount === 0)
            ) {
                throw ValidationException::withMessages([
                    'resolution' => 'Nominal refund dan payout tidak sesuai dengan snapshot booking.',
                ]);
            }

            $payment = Payment::query()
                ->where('payable_type', Payment::TYPE_BOOKING)
                ->where('payable_id', $booking->getKey())
                ->lockForUpdate()
                ->first();

            if ($customerRefundAmount > 0 && (! $payment || $payment->status === Payment::STATUS_FAILED || $payment->status === Payment::STATUS_EXPIRED)) {
                throw ValidationException::withMessages([
                    'payment' => 'Pembayaran tidak tersedia untuk refund yang diputuskan.',
                ]);
            }

            $refund = null;

            if ($customerRefundAmount > 0 && $payment) {
                $refund = $this->refunds->createRequestedForBooking(
                    $booking,
                    $payment,
                    $customerRefundAmount,
                    'dispute_resolution',
                    'Resolusi sengketa ' . $locked->reference_code . ': ' . $notes,
                    'refund:dispute:' . $locked->getKey(),
                    $admin->getKey(),
                );
            }

            $beforeStatus = $locked->status;
            $now = now('UTC');
            $bookingStatus = $ownerPayoutAmount > 0
                ? Booking::STATUS_COMPLETED
                : Booking::STATUS_CANCELLED_BY_ADMIN;

            $booking->update([
                'status' => $bookingStatus,
                'completed_at' => $ownerPayoutAmount > 0 ? $now : null,
                'completion_window_ends_at' => $now,
                'cancelled_at' => $ownerPayoutAmount === 0 ? $now : $booking->cancelled_at,
                'cancelled_by' => $ownerPayoutAmount === 0 ? $admin->getKey() : $booking->cancelled_by,
                'cancellation_reason' => $ownerPayoutAmount === 0 ? 'Dibatalkan melalui resolusi sengketa ' . $locked->reference_code : $booking->cancellation_reason,
                'slot_released_at' => $now,
            ]);

            $locked->update([
                'status' => Dispute::STATUS_RESOLVED,
                'resolution_type' => $resolutionType,
                'customer_refund_amount' => $customerRefundAmount,
                'owner_payout_amount' => $ownerPayoutAmount,
                'resolution_notes' => $notes,
                'resolved_by' => $admin->getKey(),
                'resolved_at' => $now,
            ]);

            if ($payout) {
                if ($ownerPayoutAmount === 0) {
                    $payout->update(['status' => Payout::STATUS_CANCELLED]);
                } elseif ($customerRefundAmount > 0) {
                    $payout->update([
                        'status' => Payout::STATUS_ON_HOLD,
                        'gross_amount' => $ownerPayoutAmount + (int) $booking->snapshot_gateway_fee_amount,
                        'net_amount' => $ownerPayoutAmount,
                    ]);
                } else {
                    $account = PayoutAccount::query()
                        ->where('owner_id', $payout->owner_id)
                        ->where('verification_status', PayoutAccount::STATUS_VERIFIED)
                        ->orderByDesc('verified_at')
                        ->orderByDesc('id')
                        ->first();

                    $payout->update([
                        'payout_account_id' => $account?->getKey(),
                        'gross_amount' => $ownerPayoutAmount + (int) $booking->snapshot_gateway_fee_amount,
                        'net_amount' => $ownerPayoutAmount,
                        'status' => $account ? Payout::STATUS_ELIGIBLE : Payout::STATUS_HELD,
                    ]);
                }
            }

            AuditLog::query()->create([
                'actor_user_id' => $admin->getKey(),
                'actor_role' => $admin->role,
                'action' => 'dispute.resolved',
                'auditable_type' => Dispute::class,
                'auditable_id' => $locked->getKey(),
                'reason' => $notes,
                'before_state_json' => ['status' => $beforeStatus, 'booking_status' => Booking::STATUS_DISPUTED],
                'after_state_json' => [
                    'status' => Dispute::STATUS_RESOLVED,
                    'resolution_type' => $resolutionType,
                    'customer_refund_amount' => $customerRefundAmount,
                    'owner_payout_amount' => $ownerPayoutAmount,
                    'booking_status' => $bookingStatus,
                ],
                'ip_hash' => request()->ip() ? hash('sha256', (string) request()->ip()) : null,
                'created_at' => now('UTC'),
            ]);

            return ['dispute' => $locked->refresh(), 'refund' => $refund];
        }, attempts: 3);

        if ($result['refund'] instanceof Refund) {
            $result['refund'] = $this->refunds->submit($result['refund']);
        }

        if ($result['dispute']->owner_payout_amount > 0 && ! ($result['refund'] instanceof Refund && $result['refund']->status !== Refund::STATUS_SUCCEEDED)) {
            $this->payouts->prepareEligiblePayouts();
        }

        return $result['dispute']->refresh();
    }
}
