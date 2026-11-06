<?php

namespace App\Contracts\Payments;

use App\Models\Booking;

interface PaymentGatewayInterface
{
    /**
     * Create payment session or QRIS payload for a booking.
     *
     * @return array{
     *     reference_id: string,
     *     status: 'PENDING'|'PAID'|'EXPIRED'|'FAILED',
     *     qr_payload: string,
     *     expires_at: string,
     * }
     */
    public function createPayment(Booking $booking): array;

    /**
     * Get the latest status of a payment by reference ID.
     *
     * @return 'PENDING'|'PAID'|'EXPIRED'|'FAILED'|string
     */
    public function getPaymentStatus(string $referenceId): string;

    /**
     * Simulate a successful payment callback in non-production environments.
     */
    public function simulatePaymentSuccess(string $referenceId): bool;
}
