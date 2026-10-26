<?php

namespace App\Services\Payments;

use App\Contracts\Payments\PaymentGatewayContract;
use App\Exceptions\Payments\PaymentGatewayException;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\BookingHold;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Venue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class RefundService
{
    public function __construct(private readonly PaymentGatewayContract $gateway)
    {
    }

    public function createRequestedForBooking(
        Booking $booking,
        Payment $payment,
        int $amount,
        string $reasonType,
        string $reasonNotes,
        string $idempotencyKey,
        ?int $processedBy = null,
    ): Refund {
        if (
            $booking->getKey() === null
            || $payment->payable_type !== Payment::TYPE_BOOKING
            || (int) $payment->payable_id !== (int) $booking->getKey()
            || $amount < 1
            || $amount > (int) $payment->amount
            || ! in_array($reasonType, [
                'customer_cancellation',
                'owner_cancellation',
                'late_payment_unfulfillable',
                'dispute_resolution',
            ], true)
            || trim($idempotencyKey) === ''
            || mb_strlen($idempotencyKey) > 100
        ) {
            throw ValidationException::withMessages([
                'refund' => 'Data refund tidak valid atau tidak sesuai dengan pembayaran booking.',
            ]);
        }

        $existing = Refund::query()->where('idempotency_key', $idempotencyKey)->first();

        if ($existing) {
            if (
                (int) $existing->payment_id !== (int) $payment->getKey()
                || (int) $existing->amount !== $amount
            ) {
                throw ValidationException::withMessages([
                    'idempotencyKey' => 'Kunci idempotensi refund sudah digunakan untuk kewajiban yang berbeda.',
                ]);
            }

            return $existing;
        }

        return Refund::query()->create([
            'reference_code' => 'RFD-' . now('UTC')->format('Ymd') . '-' . Str::upper(Str::random(8)),
            'booking_id' => $booking->getKey(),
            'ad_booking_id' => null,
            'payment_id' => $payment->getKey(),
            'recipient_user_id' => $booking->customer_id,
            'amount' => $amount,
            'reason_type' => $reasonType,
            'reason_notes' => trim($reasonNotes),
            'status' => Refund::STATUS_REQUESTED,
            'idempotency_key' => $idempotencyKey,
            'gateway_refund_id' => null,
            'manual_transfer_reference' => null,
            'processed_by' => $processedBy,
            'succeeded_at' => null,
        ]);
    }

    /**
     * Gateway requests are always made after the local transaction has committed.
     * Failed requests remain retryable with the same idempotency key.
     */
    public function submit(Refund $refund): Refund
    {
        $refund->refresh();

        if (in_array($refund->status, [Refund::STATUS_SUCCEEDED, Refund::STATUS_PROCESSING], true)) {
            return $refund;
        }

        $payment = $refund->payment()->firstOrFail();

        try {
            $response = $this->gateway->createRefund(
                $payment,
                (int) $refund->amount,
                $refund->idempotency_key,
            );
        } catch (PaymentGatewayException $exception) {
            return $this->markFailed($refund, $exception->isDefinitiveFailure()
                ? 'Gateway rejected the refund definitively.'
                : 'Gateway refund result is uncertain; retry with the same idempotency key.');
        } catch (Throwable $exception) {
            return $this->markFailed($refund, 'Gateway refund result is uncertain; retry with the same idempotency key.');
        }

        $gatewayRefundId = $response['gateway_refund_id'] ?? null;
        $status = $response['status'] ?? null;

        if (
            ! is_string($gatewayRefundId)
            || trim($gatewayRefundId) === ''
            || ! in_array($status, [Refund::STATUS_PROCESSING, Refund::STATUS_SUCCEEDED, Refund::STATUS_FAILED], true)
        ) {
            return $this->markFailed($refund, 'Gateway returned an incomplete refund response.');
        }

        if ($status === Refund::STATUS_SUCCEEDED) {
            return $this->markSucceeded($refund, $gatewayRefundId);
        }

        if ($status === Refund::STATUS_FAILED) {
            $refund->update([
                'gateway_refund_id' => $gatewayRefundId,
                'status' => Refund::STATUS_FAILED,
            ]);

            $this->auditRefund($refund, 'refund.gateway_failed', 'Gateway reported refund failure.');

            return $refund->refresh();
        }

        $refund->update([
            'gateway_refund_id' => $gatewayRefundId,
            'status' => Refund::STATUS_PROCESSING,
        ]);

        $this->auditRefund($refund, 'refund.gateway_processing', 'Gateway accepted the refund request; completion is not yet confirmed.');

        return $refund->refresh();
    }

    public function markSucceeded(Refund $refund, ?string $gatewayRefundId = null, ?string $manualReference = null, ?int $processedBy = null): Refund
    {
        $booking = $refund->booking()->first();
        $venueId = $booking?->venue_id;

        return DB::transaction(function () use ($refund, $booking, $venueId, $gatewayRefundId, $manualReference, $processedBy): Refund {
            if ($venueId !== null) {
                Venue::query()->whereKey($venueId)->lockForUpdate()->firstOrFail();
            }

            $lockedRefund = Refund::query()->whereKey($refund->getKey())->lockForUpdate()->firstOrFail();
            $payment = Payment::query()->whereKey($lockedRefund->payment_id)->lockForUpdate()->firstOrFail();

            if ($lockedRefund->status === Refund::STATUS_SUCCEEDED) {
                return $lockedRefund;
            }

            $lockedRefund->update([
                'status' => Refund::STATUS_SUCCEEDED,
                'gateway_refund_id' => $gatewayRefundId ?: $lockedRefund->gateway_refund_id,
                'manual_transfer_reference' => $manualReference ?: $lockedRefund->manual_transfer_reference,
                'processed_by' => $processedBy ?: $lockedRefund->processed_by,
                'succeeded_at' => now('UTC'),
            ]);

            $successfulRefundTotal = (int) Refund::query()
                ->where('payment_id', $payment->getKey())
                ->where('status', Refund::STATUS_SUCCEEDED)
                ->sum('amount');

            $payment->update([
                'status' => $successfulRefundTotal >= (int) $payment->amount
                    ? Payment::STATUS_REFUNDED
                    : Payment::STATUS_PARTIALLY_REFUNDED,
            ]);

            if ($lockedRefund->booking_id !== null) {
                $lockedBooking = Booking::query()->whereKey($lockedRefund->booking_id)->lockForUpdate()->firstOrFail();

                if (
                    $lockedBooking->status === Booking::STATUS_CANCELLED_BY_CUSTOMER
                    && $lockedBooking->slot_released_at === null
                ) {
                    $lockedBooking->update(['slot_released_at' => now('UTC')]);
                }
            }

            $this->auditRefund(
                $lockedRefund,
                'refund.succeeded',
                'Refund completion was confirmed by the gateway or recorded as a verified manual transfer.',
            );

            return $lockedRefund->refresh();
        }, attempts: 3);
    }

    private function markFailed(Refund $refund, string $reason): Refund
    {
        $refund->update(['status' => Refund::STATUS_FAILED]);
        $this->auditRefund($refund, 'refund.failed', $reason);

        return $refund->refresh();
    }

    private function auditRefund(Refund $refund, string $action, string $reason): void
    {
        AuditLog::query()->create([
            'actor_user_id' => null,
            'actor_role' => 'system',
            'action' => $action,
            'auditable_type' => Refund::class,
            'auditable_id' => $refund->getKey(),
            'reason' => $reason,
            'before_state_json' => null,
            'after_state_json' => [
                'status' => $refund->status,
                'amount' => $refund->amount,
                'payment_id' => $refund->payment_id,
                'booking_id' => $refund->booking_id,
            ],
            'ip_hash' => null,
            'created_at' => now('UTC'),
        ]);
    }
}
