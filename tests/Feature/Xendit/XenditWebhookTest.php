<?php

namespace Tests\Feature\Xendit;

use Tests\TestCase;

class XenditWebhookTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['services.xendit.callback_token' => 'test-callback-token']);
    }

    public function test_rejects_missing_or_invalid_callback_token(): void
    {
        $this->postJson('/api/webhooks/xendit', $this->payload())->assertUnauthorized();

        $this->postJson('/api/webhooks/xendit', $this->payload(), [
            'x-callback-token' => 'wrong-token',
        ])->assertUnauthorized();
    }

    public function test_accepts_valid_callback_and_deduplicates_replay(): void
    {
        $first = $this->postJson('/api/webhooks/xendit', $this->payload(), [
            'x-callback-token' => 'test-callback-token',
        ]);

        $first->assertOk()->assertJson([
            'received' => true,
            'duplicate' => false,
        ]);

        $second = $this->postJson('/api/webhooks/xendit', $this->payload(), [
            'x-callback-token' => 'test-callback-token',
        ]);

        $second->assertOk()->assertJson([
            'received' => true,
            'duplicate' => true,
        ]);
    }

    public function test_rejects_malformed_payload(): void
    {
        $this->postJson('/api/webhooks/xendit', [
            'event' => 'payment.capture',
        ], [
            'x-callback-token' => 'test-callback-token',
        ])->assertUnprocessable();
    }

    public function test_returns_service_unavailable_when_token_is_not_configured(): void
    {
        config(['services.xendit.callback_token' => null]);

        $this->postJson('/api/webhooks/xendit', $this->payload(), [
            'x-callback-token' => 'anything',
        ])->assertStatus(503);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'event' => 'payment.capture',
            'business_id' => 'business-1',
            'created' => '2026-11-05T10:00:00.000Z',
            'data' => [
                'payment_id' => 'py-123',
                'payment_request_id' => 'pr-123',
                'reference_id' => 'ADT-1',
                'currency' => 'IDR',
                'request_amount' => 150000,
                'channel_code' => 'QRIS',
                'status' => 'SUCCEEDED',
            ],
        ];
    }
}
