<?php

namespace App\Services\Payments;

use App\Contracts\Payments\PaymentGatewayInterface;
use App\Models\Booking;
use Illuminate\Support\Facades\Cache;

class FakePaymentGateway implements PaymentGatewayInterface
{
    protected const CACHE_PREFIX = 'fake_payment:status:';

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
    public function createPayment(Booking $booking): array
    {
        $referenceId = $booking->code;
        $expiresAt = $booking->expires_at?->toIso8601String() ?? now()->addMinutes(15)->toIso8601String();

        // Realistic dummy ASPI QRIS payload format
        $qrPayload = sprintf(
            '00020101021226580014ID.ADATEMPAT.WWW01189360091100222045610215%s520454995303360540%02d%s5802ID5913ADATEMPAT_PAY6007JAKARTA6304ABCD',
            str_pad(substr($referenceId, -10), 10, '0', STR_PAD_LEFT),
            strlen((string) (int) $booking->total_amount),
            (int) $booking->total_amount
        );

        $diffSeconds = $booking->expires_at !== null ? now()->diffInSeconds($booking->expires_at, false) : 900;
        $ttl = (int) max(60, $diffSeconds);

        Cache::put(self::CACHE_PREFIX.$referenceId, 'PENDING', $ttl);

        return [
            'reference_id' => $referenceId,
            'status' => 'PENDING',
            'qr_payload' => $qrPayload,
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * Get the latest status of a payment by reference ID.
     */
    public function getPaymentStatus(string $referenceId): string
    {
        return (string) Cache::get(self::CACHE_PREFIX.$referenceId, 'PENDING');
    }

    /**
     * Simulate a successful payment callback in non-production environments.
     */
    public function simulatePaymentSuccess(string $referenceId): bool
    {
        Cache::put(self::CACHE_PREFIX.$referenceId, 'PAID', now()->addHours(1));

        return true;
    }
}
