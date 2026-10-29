<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentWebhookEvent;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Subscriptions\SubscriptionBillingService;
use App\Services\Subscriptions\SubscriptionLifecycleService;
use App\Services\Payments\PaymentWebhookProcessor;
use Carbon\CarbonImmutable;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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

    public function test_downgrade_to_starter_is_scheduled_and_records_the_retained_business(): void
    {
        $owner = User::factory()->create([
            'role' => User::ROLE_OWNER,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $businesses = collect(['Satu', 'Dua'])->map(function (string $name) use ($owner): Business {
            return $owner->businesses()->create([
                'name' => 'Usaha ' . $name,
                'slug' => 'usaha-' . strtolower($name) . '-' . strtolower((string) Str::ulid()),
                'address' => 'Alamat pengujian',
                'timezone' => 'Asia/Jakarta',
                'status' => Business::STATUS_ACTIVE,
                'published_at' => now(),
            ]);
        });
        $starter = SubscriptionPlan::query()->where('slug', 'starter')->firstOrFail();
        $businessPlan = SubscriptionPlan::query()->where('slug', 'business')->firstOrFail();
        $endsAt = now('UTC')->addDays(10);
        $subscription = Subscription::query()->create([
            'owner_id' => $owner->getKey(),
            'plan_id' => $businessPlan->getKey(),
            'is_trial' => false,
            'status' => Subscription::STATUS_ACTIVE,
            'starts_at' => now('UTC')->subDays(20),
            'ends_at' => $endsAt,
            'grace_ends_at' => $endsAt->copy()->addHours(24),
        ]);

        $result = app(SubscriptionBillingService::class)->checkoutPlan(
            $owner,
            $starter,
            'subscription-downgrade-001',
            $businesses->first()->getKey(),
        );

        $this->assertTrue($result['scheduled']);
        $this->assertNull($result['invoice']);
        $this->assertNull($result['payment']);
        $this->assertSame($starter->getKey(), $subscription->fresh()->scheduled_plan_id);
        $this->assertSame($businesses->first()->getKey(), $subscription->fresh()->retained_business_id);
        $this->assertSame($endsAt->timestamp, $subscription->fresh()->ends_at->timestamp);
        $this->assertSame(Business::STATUS_ACTIVE, $businesses->last()->fresh()->status);
    }

    public function test_upgrade_prorates_remaining_days_without_changing_subscription_end_date_before_payment(): void
    {
        $owner = User::factory()->create([
            'role' => User::ROLE_OWNER,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $starter = SubscriptionPlan::query()->where('slug', 'starter')->firstOrFail();
        $business = SubscriptionPlan::query()->where('slug', 'business')->firstOrFail();
        $startsAt = now('UTC')->subDays(15)->startOfMinute();
        $endsAt = now('UTC')->addDays(15)->startOfMinute();
        $subscription = Subscription::query()->create([
            'owner_id' => $owner->getKey(),
            'plan_id' => $starter->getKey(),
            'is_trial' => false,
            'status' => Subscription::STATUS_ACTIVE,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'grace_ends_at' => $endsAt->copy()->addHours(24),
        ]);

        $result = app(SubscriptionBillingService::class)->checkoutPlan(
            $owner,
            $business,
            'subscription-upgrade-prorata-001',
        );

        $this->assertSame(Invoice::TYPE_SUBSCRIPTION_UPGRADE, $result['invoice']->invoice_type);
        $this->assertSame(150000, (int) $result['invoice']->total_amount);
        $this->assertSame(Subscription::STATUS_ACTIVE, $subscription->fresh()->status);
        $this->assertSame($endsAt->timestamp, $subscription->fresh()->ends_at->timestamp);
        $this->assertSame($starter->getKey(), $subscription->fresh()->plan_id);
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

    public function test_prepaid_downgrade_keeps_business_active_until_period_end_and_then_activates_once(): void
    {
        $owner = User::factory()->create([
            'role' => User::ROLE_OWNER,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $businesses = collect(['Satu', 'Dua'])->map(function (string $name) use ($owner): Business {
            return $owner->businesses()->create([
                'name' => 'Usaha ' . $name,
                'slug' => 'prepaid-' . strtolower($name) . '-' . strtolower((string) Str::ulid()),
                'address' => 'Alamat pengujian',
                'timezone' => 'Asia/Jakarta',
                'status' => Business::STATUS_ACTIVE,
                'published_at' => now(),
            ]);
        });
        $starter = SubscriptionPlan::query()->where('slug', 'starter')->firstOrFail();
        $businessPlan = SubscriptionPlan::query()->where('slug', 'business')->firstOrFail();
        $endsAt = now('UTC')->addDays(10)->startOfSecond();
        $subscription = Subscription::query()->create([
            'owner_id' => $owner->getKey(),
            'plan_id' => $businessPlan->getKey(),
            'is_trial' => false,
            'status' => Subscription::STATUS_ACTIVE,
            'starts_at' => $endsAt->copy()->subDays(20),
            'ends_at' => $endsAt,
            'grace_ends_at' => $endsAt->copy()->addHours(24),
        ]);
        $billing = app(SubscriptionBillingService::class);

        $scheduled = $billing->checkoutPlan($owner, $starter, 'schedule-prepaid-001', $businesses->first()->getKey());
        $this->assertTrue($scheduled['scheduled']);

        // Updating retained business must never create a payment.
        $changed = $billing->checkoutPlan($owner, $starter, 'change-retained-prepaid-001', $businesses->last()->getKey());
        $this->assertTrue($changed['scheduled']);
        $this->assertNull($changed['invoice']);
        $this->assertSame($businesses->last()->getKey(), $subscription->fresh()->retained_business_id);

        $checkout = $billing->checkoutPlan($owner, $starter, 'pay-prepaid-001');
        $this->assertFalse($checkout['scheduled']);
        $this->assertSame('renewal_downgrade', $checkout['invoice']->line_items_json['action']);
        $this->assertSame($endsAt->copy()->utc()->toIso8601String(), $checkout['invoice']->line_items_json['scheduled_effective_at']);
        $this->assertSame((int) $starter->monthly_price, (int) $checkout['invoice']->total_amount);

        $webhook = app(PaymentWebhookProcessor::class)->process('mock', [
            'event_id' => 'evt-prepaid-downgrade-001',
            'gateway_transaction_id' => $checkout['payment']->gateway_transaction_id,
            'status' => Payment::STATUS_PAID,
            'paid_at' => CarbonImmutable::now('UTC')->toIso8601String(),
            'gateway_fee_amount' => 0,
            'amount' => $checkout['payment']->amount,
        ]);

        $this->assertSame(PaymentWebhookEvent::STATUS_PROCESSED, $webhook['status']);
        $this->assertSame(Payment::STATUS_PAID, $checkout['payment']->fresh()->status);
        $this->assertSame(Invoice::STATUS_PAID, $checkout['invoice']->fresh()->status);
        $this->assertSame($checkout['invoice']->getKey(), $billing->paidScheduledRenewalInvoice($subscription->fresh())?->getKey());
        $this->assertSame($businessPlan->getKey(), $subscription->fresh()->plan_id);
        $this->assertSame($starter->getKey(), $subscription->fresh()->scheduled_plan_id);
        $this->assertSame($endsAt->timestamp, $subscription->fresh()->ends_at->timestamp);
        $this->assertSame(Business::STATUS_ACTIVE, $businesses->first()->fresh()->status);
        $this->assertSame(Business::STATUS_ACTIVE, $businesses->last()->fresh()->status);

        try {
            $billing->checkoutPlan($owner, $starter, 'duplicate-prepaid-001');
            $this->fail('A second invoice must be blocked after a prepaid Starter renewal.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('subscription', $exception->errors());
        }

        $this->assertDatabaseCount('invoices', 1);
        $this->travelTo($endsAt->copy()->addMinute());

        try {
            $lifecycle = app(SubscriptionLifecycleService::class);
            $first = $lifecycle->processDue();
            $this->assertSame(1, $first['downgraded']);
            $this->assertSame(0, $first['grace_started']);
            $this->assertSame($starter->getKey(), $subscription->fresh()->plan_id);
            $this->assertNull($subscription->fresh()->scheduled_plan_id);
            $this->assertSame($endsAt->copy()->addDays($starter->duration_days)->timestamp, $subscription->fresh()->ends_at->timestamp);
            $this->assertSame(Business::STATUS_ARCHIVED_BY_DOWNGRADE, $businesses->first()->fresh()->status);
            $this->assertSame(Business::STATUS_ACTIVE, $businesses->last()->fresh()->status);
            $this->assertSame(0, $lifecycle->processDue()['downgraded']);
            $this->assertDatabaseHas('audit_logs', [
                'auditable_type' => Subscription::class,
                'auditable_id' => $subscription->getKey(),
                'action' => 'subscription.scheduled_downgrade_applied',
            ]);
        } finally {
            $this->travelBack();
        }
    }

    public function test_scheduled_downgrade_without_payment_enters_grace_without_archiving_businesses(): void
    {
        $owner = User::factory()->create([
            'role' => User::ROLE_OWNER,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $starter = SubscriptionPlan::query()->where('slug', 'starter')->firstOrFail();
        $businessPlan = SubscriptionPlan::query()->where('slug', 'business')->firstOrFail();
        $retained = $owner->businesses()->create([
            'name' => 'Usaha Retained',
            'slug' => 'retained-no-payment-' . strtolower((string) Str::ulid()),
            'address' => 'Alamat pengujian',
            'timezone' => 'Asia/Jakarta',
            'status' => Business::STATUS_ACTIVE,
            'published_at' => now(),
        ]);
        $other = $owner->businesses()->create([
            'name' => 'Usaha Lain',
            'slug' => 'other-no-payment-' . strtolower((string) Str::ulid()),
            'address' => 'Alamat pengujian',
            'timezone' => 'Asia/Jakarta',
            'status' => Business::STATUS_ACTIVE,
            'published_at' => now(),
        ]);
        $endsAt = now('UTC')->subMinute();
        $subscription = Subscription::query()->create([
            'owner_id' => $owner->getKey(),
            'plan_id' => $businessPlan->getKey(),
            'scheduled_plan_id' => $starter->getKey(),
            'retained_business_id' => $retained->getKey(),
            'is_trial' => false,
            'status' => Subscription::STATUS_ACTIVE,
            'starts_at' => $endsAt->copy()->subDays(30),
            'ends_at' => $endsAt,
            'grace_ends_at' => $endsAt->copy()->addHours(24),
        ]);

        $result = app(SubscriptionLifecycleService::class)->processDue();

        $this->assertSame(0, $result['downgraded']);
        $this->assertSame(1, $result['grace_started']);
        $this->assertSame($businessPlan->getKey(), $subscription->fresh()->plan_id);
        $this->assertSame($starter->getKey(), $subscription->fresh()->scheduled_plan_id);
        $this->assertSame(Business::STATUS_ACTIVE, $retained->fresh()->status);
        $this->assertSame(Business::STATUS_ACTIVE, $other->fresh()->status);
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
