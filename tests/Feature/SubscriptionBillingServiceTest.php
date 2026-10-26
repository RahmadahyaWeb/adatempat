<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentWebhookEvent;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Subscriptions\SubscriptionBillingService;
use App\Services\Payments\PaymentWebhookProcessor;
use Carbon\CarbonImmutable;
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

    public function test_verified_paid_webhook_activates_subscription_and_pays_invoice(): void
    {
        $owner = User::factory()->create([
            'role' => User::ROLE_OWNER,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $plan = SubscriptionPlan::query()->where('slug', 'starter')->firstOrFail();
        $checkout = app(SubscriptionBillingService::class)->checkoutPlan(
            $owner,
            $plan,
            'subscription-webhook-success-001',
        );

        $result = app(PaymentWebhookProcessor::class)->process('mock', [
            'event_id' => 'evt-subscription-success-001',
            'gateway_transaction_id' => $checkout['payment']->gateway_transaction_id,
            'status' => Payment::STATUS_PAID,
            'paid_at' => CarbonImmutable::now('UTC')->toIso8601String(),
            'gateway_fee_amount' => 0,
            'amount' => $checkout['payment']->amount,
        ]);

        $this->assertSame(PaymentWebhookEvent::STATUS_PROCESSED, $result['status']);
        $this->assertSame(Subscription::STATUS_ACTIVE, $checkout['subscription']->fresh()->status);
        $this->assertSame(Payment::STATUS_PAID, $checkout['payment']->fresh()->status);
        $this->assertSame(Invoice::STATUS_PAID, $checkout['invoice']->fresh()->status);
        $this->assertDatabaseHas('ledger_entries', [
            'reference_type' => 'invoice',
            'reference_id' => $checkout['invoice']->getKey(),
            'account_code' => 'subscription_revenue',
            'entry_type' => 'credit',
            'amount' => $checkout['invoice']->total_amount,
        ]);
    }

    public function test_paid_webhook_with_wrong_amount_requires_reconciliation_without_activation(): void
    {
        $owner = User::factory()->create([
            'role' => User::ROLE_OWNER,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $plan = SubscriptionPlan::query()->where('slug', 'starter')->firstOrFail();
        $checkout = app(SubscriptionBillingService::class)->checkoutPlan(
            $owner,
            $plan,
            'subscription-webhook-wrong-amount-001',
        );

        $result = app(PaymentWebhookProcessor::class)->process('mock', [
            'event_id' => 'evt-subscription-wrong-amount-001',
            'gateway_transaction_id' => $checkout['payment']->gateway_transaction_id,
            'status' => Payment::STATUS_PAID,
            'paid_at' => CarbonImmutable::now('UTC')->toIso8601String(),
            'gateway_fee_amount' => 0,
            'amount' => max(1, (int) $checkout['payment']->amount - 1),
        ]);

        $this->assertSame(PaymentWebhookEvent::STATUS_PROCESSED, $result['status']);
        $this->assertSame(Subscription::STATUS_PENDING_PAYMENT, $checkout['subscription']->fresh()->status);
        $this->assertSame(Payment::STATUS_RECONCILIATION_REQUIRED, $checkout['payment']->fresh()->status);
        $this->assertSame(Invoice::STATUS_UNPAID, $checkout['invoice']->fresh()->status);
        $this->assertDatabaseHas('payment_webhook_events', [
            'gateway_event_id' => 'evt-subscription-wrong-amount-001',
            'processing_status' => PaymentWebhookEvent::STATUS_PROCESSED,
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
