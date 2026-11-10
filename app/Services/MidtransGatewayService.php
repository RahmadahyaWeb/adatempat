<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MidtransGatewayService
{
    protected string $serverKey;

    protected string $apiUrl;

    public function __construct()
    {
        $this->serverKey = (string) Config::get('services.midtrans.server_key', '');
        $this->apiUrl = rtrim((string) Config::get('services.midtrans.api_url', 'https://api.sandbox.midtrans.com/v2'), '/');
    }

    /**
     * Send dynamic QRIS charge request to Midtrans Core API.
     *
     * @return array{
     *     transaction_id: string|null,
     *     order_id: string,
     *     gross_amount: int,
     *     qr_string: string|null,
     *     qr_url: string|null,
     *     expiry_time: string|null,
     *     transaction_status: string,
     *     raw: array<string, mixed>
     * }
     */
    public function chargeDynamicQris(Booking $booking): array
    {
        $orderTime = ($booking->created_at ? Carbon::parse($booking->created_at) : now())->format('Y-m-d H:i:s O');

        $payload = [
            'payment_type' => 'qris',
            'transaction_details' => [
                'order_id' => $booking->booking_code,
                'gross_amount' => (int) $booking->total_amount,
            ],
            'custom_expiry' => [
                'order_time' => $orderTime,
                'expiry_duration' => 10,
                'unit' => 'minute',
            ],
        ];

        $response = Http::withHeaders([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Authorization' => 'Basic '.base64_encode($this->serverKey.':'),
        ])->post("{$this->apiUrl}/charge", $payload);

        if ($response->failed()) {
            throw new RuntimeException("Midtrans charge failed [{$response->status()}]: {$response->body()}");
        }

        $data = $response->json();

        $qrUrl = null;
        if (isset($data['actions']) && is_array($data['actions'])) {
            foreach ($data['actions'] as $action) {
                if (isset($action['name']) && $action['name'] === 'generate-qr-code') {
                    $qrUrl = $action['url'] ?? null;
                    break;
                }
            }
        }

        $grossAmount = isset($data['gross_amount'])
            ? (int) round((float) $data['gross_amount'])
            : (int) $booking->total_amount;

        return [
            'transaction_id' => $data['transaction_id'] ?? null,
            'order_id' => $data['order_id'] ?? $booking->booking_code,
            'gross_amount' => $grossAmount,
            'qr_string' => $data['qr_string'] ?? null,
            'qr_url' => $qrUrl,
            'expiry_time' => $data['expiry_time'] ?? null,
            'transaction_status' => $data['transaction_status'] ?? 'pending',
            'raw' => $data,
        ];
    }

    /**
     * Authenticate inbound Midtrans webhook or status response using timing-safe SHA-512 comparison.
     *
     * @param  array<string, mixed>  $payload
     */
    public function verifyWebhookSignature(array $payload): bool
    {
        if (
            empty($payload['order_id']) ||
            empty($payload['status_code']) ||
            ! isset($payload['gross_amount']) ||
            empty($payload['signature_key'])
        ) {
            return false;
        }

        $expectedSignature = hash(
            'sha512',
            (string) $payload['order_id'].
            (string) $payload['status_code'].
            (string) $payload['gross_amount'].
            $this->serverKey
        );

        return hash_equals($expectedSignature, (string) $payload['signature_key']);
    }

    /**
     * Fetch real-time transaction status from Midtrans Status API.
     *
     * @return array<string, mixed>
     */
    public function fetchTransactionStatus(string $orderId): array
    {
        $response = Http::withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Basic '.base64_encode($this->serverKey.':'),
        ])->get("{$this->apiUrl}/{$orderId}/status");

        if ($response->failed()) {
            throw new RuntimeException("Midtrans status inquiry failed [{$response->status()}]: {$response->body()}");
        }

        return $response->json();
    }
}
