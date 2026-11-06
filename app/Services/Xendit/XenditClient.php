<?php

namespace App\Services\Xendit;

use Carbon\CarbonInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class XenditClient
{
    private const API_VERSION = '2024-11-11';

    private string $secretKey;

    private string $baseUrl;

    public function __construct()
    {
        $this->secretKey = (string) config('services.xendit.secret_key');
        $this->baseUrl = rtrim((string) config('services.xendit.base_url', 'https://api.xendit.co'), '/');
    }

    public function isConfigured(): bool
    {
        return $this->secretKey !== '';
    }

    /**
     * @return array<string, mixed>
     */
    public function createQris(
        string $referenceId,
        int $amount,
        CarbonInterface $expiresAt,
        ?string $description = null,
        ?string $subAccountId = null,
    ): array {
        $payload = [
            'reference_id' => $referenceId,
            'type' => 'PAY',
            'country' => 'ID',
            'currency' => 'IDR',
            'request_amount' => $amount,
            'capture_method' => 'AUTOMATIC',
            'channel_code' => 'QRIS',
            'channel_properties' => [
                'expires_at' => $expiresAt->toIso8601ZuluString(),
            ],
        ];

        if ($description !== null && $description !== '') {
            $payload['description'] = $description;
        }

        /** @var array<string, mixed> $body */
        $body = $this->request($subAccountId)
            ->post('/v3/payment_requests', $payload)
            ->throw()
            ->json();

        return $body;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPaymentRequest(string $paymentRequestId): array
    {
        /** @var array<string, mixed> $body */
        $body = $this->request()
            ->get("/v3/payment_requests/{$paymentRequestId}")
            ->throw()
            ->json();

        return $body;
    }

    /**
     * @return array<string, mixed>
     */
    public function simulatePayment(string $paymentRequestId, int $amount): array
    {
        /** @var array<string, mixed> $body */
        $body = $this->request()
            ->post("/v3/payment_requests/{$paymentRequestId}/simulate", [
                'amount' => $amount,
            ])
            ->throw()
            ->json();

        return $body;
    }

    /**
     * @return array<string, mixed>
     */
    public function createRefund(
        string $paymentId,
        string $referenceId,
        int $amount,
        ?string $subAccountId = null,
    ): array {
        /** @var array<string, mixed> $body */
        $body = $this->request($subAccountId)
            ->post('/v3/refunds', [
                'reference_id' => $referenceId,
                'payment_id' => $paymentId,
                'amount' => $amount,
                'reason' => 'REQUESTED_BY_CUSTOMER',
            ])
            ->throw()
            ->json();

        return $body;
    }

    /**
     * Test mode only: sub-accounts are provisioned immediately with status LIVE.
     *
     * @return array<string, mixed>
     */
    public function createTestSubAccount(string $name, string $email, string $country = 'ID'): array
    {
        /** @var array<string, mixed> $body */
        $body = $this->request()
            ->post('/v3/accounts', [
                'name' => $name,
                'email' => $email,
                'identity' => [
                    'country_of_incorporation' => $country,
                    'entity_type' => 'CORPORATION',
                ],
            ])
            ->throw()
            ->json();

        return $body;
    }

    private function request(?string $subAccountId = null): PendingRequest
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('XENDIT_SECRET_KEY is not configured.');
        }

        $request = Http::baseUrl($this->baseUrl)
            ->withBasicAuth($this->secretKey, '')
            ->withHeaders(['api-version' => self::API_VERSION])
            ->acceptJson()
            ->timeout(20);

        if ($subAccountId !== null && $subAccountId !== '') {
            $request = $request->withHeaders(['for-user-id' => $subAccountId]);
        }

        return $request;
    }
}
