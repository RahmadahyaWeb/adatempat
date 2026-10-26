<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_unsigned_webhook_is_rejected_before_payload_processing(): void
    {
        $this->postJson('/api/webhooks/payments/mock', [
            'event_id' => 'evt-invalid-signature',
            'gateway_transaction_id' => 'MOCK-INVALID',
            'status' => 'paid',
            'paid_at' => now()->toIso8601String(),
            'amount' => 1000,
            'gateway_fee_amount' => 0,
        ], [
            'X-Payment-Signature' => 'invalid-signature',
        ])->assertUnauthorized();

        $this->assertDatabaseCount('payment_webhook_events', 0);
    }
}
