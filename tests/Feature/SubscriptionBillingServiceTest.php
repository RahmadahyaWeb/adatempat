<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Subscriptions\SubscriptionBillingService;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionBillingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SubscriptionPlanSeeder::class);
    }

    public function test_initial_purchase_creates_invoice_and_pending_payment_without_activating_subscription(): void
    {
        $owner = User::factory()->create([
            'role' => User::ROLE_OWNER,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $plan = SubscriptionPlan::query()->where('slug', 'starter')->firstOrFail();

        $result = app(SubscriptionBillingService::class)->checkoutPlan(
            $owner,
            $plan,
            'subscription-purchase-test-001',
        );

        $this->assertFalse($result['scheduled']);
        $this->assertSame(Invoice::STATUS_UNPAID, $result['invoice']->status);
        $this->assertSame((int) $plan->monthly_price, (int) $result['invoice']->total_amount);
        $this->assertSame(Invoice::TYPE_SUBSCRIPTION, $result['invoice']->invoice_type);
        $this->assertSame(Payment::TYPE_SUBSCRIPTION, $result['payment']->payable_type);
        $this->assertSame(Payment::STATUS_PENDING, $result['payment']->status);
        $this->assertSame(Subscription::STATUS_PENDING_PAYMENT, $result['subscription']->status);
        $this->assertNotNull($result['payment']->gateway_transaction_id);
        $this->assertNotNull($result['payment']->qris_payload);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Subscription::class,
            'auditable_id' => $result['subscription']->getKey(),
            'action' => 'subscription.purchase_started',
        ]);
    }

    public function test_same_idempotency_key_returns_the_existing_checkout_instead_of_creating_a_second_invoice(): void
    {
        $owner = User::factory()->create([
            'role' => User::ROLE_OWNER,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $plan = SubscriptionPlan::query()->where('slug', 'starter')->firstOrFail();
        $service = app(SubscriptionBillingService::class);

        $first = $service->checkoutPlan($owner, $plan, 'subscription-idempotency-test-001');
        $second = $service->checkoutPlan($owner, $plan, 'subscription-idempotency-test-001');

        $this->assertSame($first['payment']->getKey(), $second['payment']->getKey());
        $this->assertSame($first['subscription']->getKey(), $second['subscription']->getKey());
        $this->assertSame($first['invoice']->getKey(), $second['invoice']->getKey());
        $this->assertDatabaseCount('subscriptions', 1);
        $this->assertDatabaseCount('invoices', 1);
        $this->assertDatabaseCount('payments', 1);
    }
}
