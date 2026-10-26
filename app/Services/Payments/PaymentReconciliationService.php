<?php

namespace App\Services\Payments;

use App\Contracts\Payments\PaymentGatewayContract;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\BookingHold;
use App\Models\Payment;
use App\Models\Venue;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Throwable;

class PaymentReconciliationService
{
    public function __construct(private readonly PaymentGatewayContract $gateway)
    {
    }

    /**
     * Expire active holds locally before querying the gateway. Every gateway query happens
     * after the transaction that changes the hold to reconciling has committed.
     *
     * @return array{scanned:int,released:int,reconciling:int,skipped:int}
     */
    public function reconcileExpiredHolds(int $limit = 100): array
    {
        $now = CarbonImmutable::now('UTC');
        $holdIds = BookingHold::query()
            ->where('status', BookingHold::STATUS_ACTIVE)
            ->where('expires_at', '<=', $now)
            ->orderBy('id')
            ->limit(max(1, min($limit, 500)))
            ->pluck('id');

        $result = [
            'scanned' => $holdIds->count(),
            'released' => 0,
            'reconciling' => 0,
            'skipped' => 0,
        ];

        foreach ($holdIds as $holdId) {
            $prepared = $this->markHoldReconciling((int) $holdId);

            if (! $prepared) {
                $result['skipped']++;

                continue;
            }

            $payment = Payment::query()
                ->where('payable_type', Payment::TYPE_BOOKING)
                ->where('payable_id', $prepared['booking_id'])
                ->first();

            if (! $payment) {
                $this->markMissingPayment($prepared);

                $result['reconciling']++;

                continue;
            }

            try {
                $gatewayStatus = $this->gateway->getChargeStatus($payment->fresh());
            } catch (Throwable $exception) {
                $gatewayStatus = [
                    'status' => 'unknown',
                    'gateway_transaction_id' => $payment->gateway_transaction_id,
                    'gateway_fee_amount' => (int) $payment->gateway_fee_amount,
                    'paid_at' => null,
                ];
            }

            $outcome = $this->applyGatewayStatus($prepared, $payment, $gatewayStatus);

            if ($outcome === 'released') {
                $result['released']++;
            } elseif ($outcome === 'reconciling') {
                $result['reconciling']++;
            } else {
                $result['skipped']++;
            }
        }

        return $result;
    }

    private function markHoldReconciling(int $holdId): ?array
    {
        $initial = BookingHold::query()->find($holdId);

        if (! $initial) {
            return null;
        }

        return DB::transaction(function () use ($holdId, $initial): ?array {
            Venue::query()->whereKey($initial->venue_id)->lockForUpdate()->firstOrFail();

            $hold = BookingHold::query()->whereKey($holdId)->lockForUpdate()->firstOrFail();

            if (
                $hold->status !== BookingHold::STATUS_ACTIVE
                || $hold->expires_at->isFuture()
            ) {
                return null;
            }

            $booking = Booking::query()->whereKey($hold->booking_id)->lockForUpdate()->firstOrFail();

            $hold->update(['status' => BookingHold::STATUS_RECONCILING]);

            AuditLog::query()->create([
                'actor_user_id' => null,
                'actor_role' => 'system',
                'action' => 'booking.hold_expired_reconciling',
                'auditable_type' => BookingHold::class,
                'auditable_id' => $hold->getKey(),
                'reason' => 'Hold deadline passed; gateway status must be queried before releasing the slot.',
                'before_state_json' => ['status' => BookingHold::STATUS_ACTIVE],
                'after_state_json' => ['status' => BookingHold::STATUS_RECONCILING],
                'ip_hash' => null,
                'created_at' => now('UTC'),
            ]);

            return [
                'hold_id' => $hold->getKey(),
                'venue_id' => $hold->venue_id,
                'booking_id' => $booking->getKey(),
                'deadline' => CarbonImmutable::instance($hold->expires_at)->utc(),
            ];
        }, attempts: 3);
    }

    private function applyGatewayStatus(array $prepared, Payment $payment, array $gatewayStatus): string
    {
        return DB::transaction(function () use ($prepared, $payment, $gatewayStatus): string {
            Venue::query()->whereKey($prepared['venue_id'])->lockForUpdate()->firstOrFail();

            $lockedPayment = Payment::query()->whereKey($payment->getKey())->lockForUpdate()->firstOrFail();
            $booking = Booking::query()->whereKey($prepared['booking_id'])->lockForUpdate()->firstOrFail();
            $hold = BookingHold::query()->whereKey($prepared['hold_id'])->lockForUpdate()->firstOrFail();

            if ($hold->status !== BookingHold::STATUS_RECONCILING) {
                return 'skipped';
            }

            $gatewayState = $gatewayStatus['status'] ?? 'unknown';

            if (in_array($gatewayState, [Payment::STATUS_FAILED, Payment::STATUS_EXPIRED], true)) {
                if ($lockedPayment->status === Payment::STATUS_PAID || $booking->status === Booking::STATUS_CONFIRMED) {
                    $booking->update(['status' => Booking::STATUS_RECONCILIATION_REQUIRED]);
                    $this->auditReconciliation($lockedPayment, $booking, $hold, 'Gateway expiry result conflicts with a previously paid or confirmed booking.');

                    return 'reconciling';
                }

                $paymentStatus = $gatewayState === Payment::STATUS_EXPIRED
                    ? Payment::STATUS_EXPIRED
                    : Payment::STATUS_FAILED;
                $lockedPayment->update([
                    'status' => $paymentStatus,
                    'qris_payload' => null,
                ]);
                $booking->update([
                    'status' => Booking::STATUS_EXPIRED,
                    'slot_released_at' => now('UTC'),
                ]);
                $hold->update(['status' => BookingHold::STATUS_RELEASED]);

                AuditLog::query()->create([
                    'actor_user_id' => null,
                    'actor_role' => 'system',
                    'action' => 'booking.hold_released_after_gateway_expiry',
                    'auditable_type' => Booking::class,
                    'auditable_id' => $booking->getKey(),
                    'reason' => 'Gateway confirmed the charge failed or expired after local hold deadline.',
                    'before_state_json' => [
                        'payment_status' => $payment->status,
                        'booking_status' => $booking->getOriginal('status'),
                        'hold_status' => BookingHold::STATUS_RECONCILING,
                    ],
                    'after_state_json' => [
                        'payment_status' => $paymentStatus,
                        'booking_status' => Booking::STATUS_EXPIRED,
                        'hold_status' => BookingHold::STATUS_RELEASED,
                    ],
                    'ip_hash' => null,
                    'created_at' => now('UTC'),
                ]);

                return 'released';
            }

            if ($gatewayState === Payment::STATUS_PAID) {
                $paidAt = $gatewayStatus['paid_at'] ?? null;
                $updates = [
                    'status' => Payment::STATUS_RECONCILIATION_REQUIRED,
                    'qris_payload' => null,
                ];

                if ($paidAt instanceof \DateTimeInterface) {
                    $updates['paid_at'] = CarbonImmutable::instance($paidAt)->utc();
                }

                if (isset($gatewayStatus['gateway_transaction_id']) && $gatewayStatus['gateway_transaction_id']) {
                    $updates['gateway_transaction_id'] = $gatewayStatus['gateway_transaction_id'];
                }

                if (isset($gatewayStatus['gateway_fee_amount'])) {
                    $updates['gateway_fee_amount'] = max(0, (int) $gatewayStatus['gateway_fee_amount']);
                }

                $lockedPayment->update($updates);
                $booking->update(['status' => Booking::STATUS_RECONCILIATION_REQUIRED]);
                $this->auditReconciliation(
                    $lockedPayment,
                    $booking,
                    $hold,
                    'Gateway reports payment after local hold deadline; controlled reconciliation is required before confirmation.',
                );

                return 'reconciling';
            }

            $lockedPayment->update([
                'status' => Payment::STATUS_RECONCILIATION_REQUIRED,
                'qris_payload' => null,
            ]);
            $booking->update(['status' => Booking::STATUS_RECONCILIATION_REQUIRED]);
            $this->auditReconciliation(
                $lockedPayment,
                $booking,
                $hold,
                'Gateway status remains pending or unknown after the local hold deadline.',
            );

            return 'reconciling';
        }, attempts: 3);
    }

    private function markMissingPayment(array $prepared): void
    {
        DB::transaction(function () use ($prepared): void {
            Venue::query()->whereKey($prepared['venue_id'])->lockForUpdate()->firstOrFail();

            $booking = Booking::query()->whereKey($prepared['booking_id'])->lockForUpdate()->firstOrFail();
            $hold = BookingHold::query()->whereKey($prepared['hold_id'])->lockForUpdate()->firstOrFail();

            $booking->update(['status' => Booking::STATUS_RECONCILIATION_REQUIRED]);

            $this->auditReconciliation(
                null,
                $booking,
                $hold,
                'Expired hold has no associated payment; manual reconciliation is required.',
            );
        }, attempts: 3);
    }

    private function auditReconciliation(
        ?Payment $payment,
        Booking $booking,
        BookingHold $hold,
        string $reason,
    ): void {
        AuditLog::query()->create([
            'actor_user_id' => null,
            'actor_role' => 'system',
            'action' => 'booking.payment_reconciliation_required',
            'auditable_type' => Booking::class,
            'auditable_id' => $booking->getKey(),
            'reason' => $reason,
            'before_state_json' => [
                'payment_status' => $payment?->status,
                'booking_status' => $booking->getOriginal('status'),
                'hold_status' => $hold->getOriginal('status'),
            ],
            'after_state_json' => [
                'payment_status' => $payment?->status,
                'booking_status' => $booking->status,
                'hold_status' => $hold->status,
            ],
            'ip_hash' => null,
            'created_at' => now('UTC'),
        ]);
    }
}
