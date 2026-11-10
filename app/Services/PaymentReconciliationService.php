<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Enums\SlotStatus;
use App\Models\Booking;
use App\Models\CourtSlot;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PaymentReconciliationService
{
    public function __construct(
        protected MidtransGatewayService $gateway,
        protected BookingConcurrencyService $concurrencyService,
    ) {}

    /**
     * Initiate Midtrans Dynamic QRIS charge and persist payment audit record.
     */
    public function initiateQrisPayment(Booking $booking): Payment
    {
        // Reuse existing unexpired pending QRIS payment if available
        $existingPayment = Payment::query()
            ->where('booking_id', $booking->id)
            ->where('status', 'pending')
            ->whereNotNull('qr_string')
            ->where(function ($query) {
                $query->whereNull('expiry_time')
                    ->orWhere('expiry_time', '>', now());
            })
            ->latest('id')
            ->first();

        if ($existingPayment) {
            return $existingPayment;
        }

        $charge = $this->gateway->chargeDynamicQris($booking);

        return DB::transaction(function () use ($booking, $charge): Payment {
            $expiryTime = ! empty($charge['expiry_time'])
                ? Carbon::parse($charge['expiry_time'])
                : $booking->qris_expires_at;

            $booking->update([
                'midtrans_transaction_id' => $charge['transaction_id'],
                'qris_url' => $charge['qr_url'],
                'qris_expires_at' => $expiryTime,
            ]);

            return Payment::create([
                'booking_id' => $booking->id,
                'order_id' => $booking->booking_code,
                'midtrans_transaction_id' => $charge['transaction_id'],
                'gross_amount' => $charge['gross_amount'],
                'payment_type' => 'qris',
                'status' => $charge['transaction_status'] ?? 'pending',
                'qr_string' => $charge['qr_string'],
                'qr_url' => $charge['qr_url'],
                'expiry_time' => $expiryTime,
                'raw_payload' => $charge['raw'],
            ]);
        });
    }

    /**
     * Process inbound Midtrans webhook notification or inquiry payload with pessimistic locking and idempotency.
     *
     * @param  array<string, mixed>  $payload
     * @return array{status: string, booking_status: string, idempotent: bool}
     *
     * @throws HttpException
     */
    public function processWebhook(array $payload): array
    {
        if (! $this->gateway->verifyWebhookSignature($payload)) {
            throw new HttpException(401, 'Invalid Midtrans signature.');
        }

        $orderId = (string) ($payload['order_id'] ?? '');
        if (empty($orderId)) {
            throw new HttpException(400, 'Missing order_id in notification payload.');
        }

        $transactionStatus = (string) ($payload['transaction_status'] ?? '');
        $fraudStatus = (string) ($payload['fraud_status'] ?? 'accept');

        return DB::transaction(function () use ($payload, $orderId, $transactionStatus, $fraudStatus): array {
            $booking = Booking::query()
                ->where('booking_code', $orderId)
                ->lockForUpdate()
                ->first();

            if (! $booking) {
                throw new HttpException(404, "Booking with code {$orderId} not found.");
            }

            // Strictly idempotent: If booking is already paid, acknowledge HTTP 200 without duplicate transitions
            if ($booking->payment_status === PaymentStatus::Paid) {
                return [
                    'status' => 'success',
                    'booking_status' => PaymentStatus::Paid->value,
                    'idempotent' => true,
                ];
            }

            $slotIds = $booking->items()->pluck('court_slot_id')->all();

            $isSettlement = $transactionStatus === 'settlement' || ($transactionStatus === 'capture' && $fraudStatus === 'accept');
            $isCancelledOrExpired = in_array($transactionStatus, ['expire', 'cancel', 'deny'], true);

            if ($isSettlement) {
                if ($booking->payment_status !== PaymentStatus::Pending) {
                    throw new HttpException(409, "Cannot settle booking in [{$booking->payment_status->value}] status.");
                }

                $settledAt = ! empty($payload['settlement_time'])
                    ? Carbon::parse((string) $payload['settlement_time'])
                    : now();

                $booking->update([
                    'payment_status' => PaymentStatus::Paid,
                    'paid_at' => $settledAt,
                    'midtrans_transaction_id' => $payload['transaction_id'] ?? $booking->midtrans_transaction_id,
                ]);

                // Commit court slots to booked
                CourtSlot::query()
                    ->whereIn('id', $slotIds)
                    ->lockForUpdate()
                    ->update([
                        'status' => SlotStatus::Booked,
                        'locked_until' => null,
                    ]);

                // Record or update payment audit trail
                $this->recordPaymentAudit(
                    booking: $booking,
                    orderId: $orderId,
                    status: 'settlement',
                    payload: $payload,
                    settledAt: $settledAt,
                );

                // Release Redis hold locks
                $this->concurrencyService->releaseRedisLocks($slotIds);

                return [
                    'status' => 'success',
                    'booking_status' => PaymentStatus::Paid->value,
                    'idempotent' => false,
                ];
            }

            if ($isCancelledOrExpired) {
                if ($booking->payment_status === PaymentStatus::Pending) {
                    $booking->update([
                        'payment_status' => PaymentStatus::Expired,
                    ]);

                    // Reset held court slots to available
                    CourtSlot::query()
                        ->whereIn('id', $slotIds)
                        ->where('status', SlotStatus::Held)
                        ->lockForUpdate()
                        ->update([
                            'status' => SlotStatus::Available,
                            'locked_until' => null,
                        ]);

                    // Record payment audit trail
                    $this->recordPaymentAudit(
                        booking: $booking,
                        orderId: $orderId,
                        status: $transactionStatus,
                        payload: $payload,
                    );

                    // Release Redis hold locks
                    $this->concurrencyService->releaseRedisLocks($slotIds);
                }

                return [
                    'status' => 'success',
                    'booking_status' => PaymentStatus::Expired->value,
                    'idempotent' => false,
                ];
            }

            // Other statuses (e.g. pending)
            $this->recordPaymentAudit(
                booking: $booking,
                orderId: $orderId,
                status: $transactionStatus,
                payload: $payload,
            );

            return [
                'status' => 'success',
                'booking_status' => $booking->payment_status->value,
                'idempotent' => false,
            ];
        });
    }

    /**
     * Reconcile booking status on-demand against Midtrans Status API.
     */
    public function inquireAndReconcile(Booking $booking): Booking
    {
        $statusPayload = $this->gateway->fetchTransactionStatus($booking->booking_code);

        $this->processWebhook($statusPayload);

        return $booking->fresh(['items.courtSlot', 'payments']);
    }

    /**
     * Record or update payment gateway audit trail.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function recordPaymentAudit(
        Booking $booking,
        string $orderId,
        string $status,
        array $payload,
        ?Carbon $settledAt = null,
    ): Payment {
        $grossAmount = isset($payload['gross_amount'])
            ? (int) round((float) $payload['gross_amount'])
            : (int) $booking->total_amount;

        $payment = Payment::query()
            ->where('booking_id', $booking->id)
            ->where('order_id', $orderId)
            ->latest('id')
            ->first();

        if ($payment) {
            $payment->update([
                'midtrans_transaction_id' => $payload['transaction_id'] ?? $payment->midtrans_transaction_id,
                'gross_amount' => $grossAmount,
                'status' => $status,
                'settled_at' => $settledAt ?? $payment->settled_at,
                'raw_payload' => $payload,
            ]);

            return $payment;
        }

        return Payment::create([
            'booking_id' => $booking->id,
            'order_id' => $orderId,
            'midtrans_transaction_id' => $payload['transaction_id'] ?? null,
            'gross_amount' => $grossAmount,
            'payment_type' => $payload['payment_type'] ?? 'qris',
            'status' => $status,
            'qr_string' => $payload['qr_string'] ?? null,
            'qr_url' => null,
            'expiry_time' => ! empty($payload['expiry_time']) ? Carbon::parse((string) $payload['expiry_time']) : null,
            'settled_at' => $settledAt,
            'raw_payload' => $payload,
        ]);
    }
}
