<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingHold;
use App\Models\Business;
use App\Models\Category;
use App\Models\LegalDocument;
use App\Models\Payment;
use App\Models\PaymentWebhookEvent;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\TermsAcceptance;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueAvailabilityRule;
use App\Models\VenuePrice;
use App\Services\Booking\BookingEngineService;
use App\Services\Payments\PaymentWebhookProcessor;
use Carbon\CarbonImmutable;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentWebhookProcessorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CategorySeeder::class);
    }

    public function test_valid_paid_event_confirms_booking_and_converts_hold(): void
    {
        [$customer, $venue, $acceptance] = $this->createCheckoutContext();
        [$starts, $ends] = $this->futureRange();
        $checkout = app(BookingEngineService::class)->createCheckout(
            $customer,
            $venue,
            $starts->utc(),
            $ends->utc(),
            $acceptance->getKey(),
            'webhook-paid-001',
        );
        $payment = $checkout['payment'];

        $result = app(PaymentWebhookProcessor::class)->process('mock', [
            'event_id' => 'evt-paid-001',
            'gateway_transaction_id' => $payment->gateway_transaction_id,
            'status' => Payment::STATUS_PAID,
            'paid_at' => CarbonImmutable::now('UTC')->toIso8601String(),
            'gateway_fee_amount' => 2500,
            'amount' => $payment->amount,
        ]);

        $this->assertSame(PaymentWebhookEvent::STATUS_PROCESSED, $result['status']);
        $this->assertDatabaseHas('payments', [
            'id' => $payment->getKey(),
            'status' => Payment::STATUS_PAID,
            'gateway_fee_amount' => 2500,
        ]);
        $this->assertDatabaseHas('bookings', [
            'id' => $checkout['booking']->getKey(),
            'status' => Booking::STATUS_CONFIRMED,
            'snapshot_gateway_fee_amount' => 2500,
            'snapshot_net_owner_entitlement' => 97500,
        ]);
        $this->assertDatabaseHas('booking_holds', [
            'id' => $checkout['hold']->getKey(),
            'status' => BookingHold::STATUS_CONVERTED,
        ]);
    }

    public function test_duplicate_event_is_idempotent_and_only_increments_delivery_count(): void
    {
        [$customer, $venue, $acceptance] = $this->createCheckoutContext();
        [$starts, $ends] = $this->futureRange();
        $checkout = app(BookingEngineService::class)->createCheckout(
            $customer,
            $venue,
            $starts->utc(),
            $ends->utc(),
            $acceptance->getKey(),
            'webhook-duplicate-001',
        );
        $payment = $checkout['payment'];
        $event = [
            'event_id' => 'evt-duplicate-001',
            'gateway_transaction_id' => $payment->gateway_transaction_id,
            'status' => Payment::STATUS_PAID,
            'paid_at' => CarbonImmutable::now('UTC')->toIso8601String(),
            'gateway_fee_amount' => 0,
            'amount' => $payment->amount,
        ];

        app(PaymentWebhookProcessor::class)->process('mock', $event);
        $second = app(PaymentWebhookProcessor::class)->process('mock', $event);

        $this->assertTrue($second['duplicate']);
        $this->assertDatabaseHas('payment_webhook_events', [
            'gateway_provider' => 'mock',
            'gateway_event_id' => 'evt-duplicate-001',
            'processing_status' => PaymentWebhookEvent::STATUS_PROCESSED,
            'duplicate_delivery_count' => 1,
        ]);
        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_paid_event_after_hold_deadline_enters_reconciliation_without_confirmation(): void
    {
        [$customer, $venue, $acceptance] = $this->createCheckoutContext();
        [$starts, $ends] = $this->futureRange();
        $checkout = app(BookingEngineService::class)->createCheckout(
            $customer,
            $venue,
            $starts->utc(),
            $ends->utc(),
            $acceptance->getKey(),
            'webhook-late-001',
        );
        $payment = $checkout['payment'];
        $deadline = CarbonImmutable::now('UTC')->subMinute();

        $checkout['hold']->update(['expires_at' => $deadline]);

        $result = app(PaymentWebhookProcessor::class)->process('mock', [
            'event_id' => 'evt-late-001',
            'gateway_transaction_id' => $payment->gateway_transaction_id,
            'status' => Payment::STATUS_PAID,
            'paid_at' => $deadline->subMinute()->toIso8601String(),
            'gateway_fee_amount' => 0,
            'amount' => $payment->amount,
        ]);

        $this->assertSame(PaymentWebhookEvent::STATUS_PROCESSED, $result['status']);
        $this->assertDatabaseHas('payments', [
            'id' => $payment->getKey(),
            'status' => Payment::STATUS_RECONCILIATION_REQUIRED,
        ]);
        $this->assertDatabaseHas('bookings', [
            'id' => $checkout['booking']->getKey(),
            'status' => Booking::STATUS_RECONCILIATION_REQUIRED,
        ]);
        $this->assertDatabaseHas('booking_holds', [
            'id' => $checkout['hold']->getKey(),
            'status' => BookingHold::STATUS_RECONCILING,
        ]);
    }

    public function test_amount_mismatch_enters_reconciliation(): void
    {
        [$customer, $venue, $acceptance] = $this->createCheckoutContext();
        [$starts, $ends] = $this->futureRange();
        $checkout = app(BookingEngineService::class)->createCheckout(
            $customer,
            $venue,
            $starts->utc(),
            $ends->utc(),
            $acceptance->getKey(),
            'webhook-amount-001',
        );
        $payment = $checkout['payment'];

        app(PaymentWebhookProcessor::class)->process('mock', [
            'event_id' => 'evt-amount-001',
            'gateway_transaction_id' => $payment->gateway_transaction_id,
            'status' => Payment::STATUS_PAID,
            'paid_at' => CarbonImmutable::now('UTC')->toIso8601String(),
            'gateway_fee_amount' => 0,
            'amount' => $payment->amount + 1,
        ]);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->getKey(),
            'status' => Payment::STATUS_RECONCILIATION_REQUIRED,
        ]);
        $this->assertDatabaseHas('bookings', [
            'id' => $checkout['booking']->getKey(),
            'status' => Booking::STATUS_RECONCILIATION_REQUIRED,
        ]);
    }

    public function test_failed_event_releases_hold_and_booking_slot(): void
    {
        [$customer, $venue, $acceptance] = $this->createCheckoutContext();
        [$starts, $ends] = $this->futureRange();
        $checkout = app(BookingEngineService::class)->createCheckout(
            $customer,
            $venue,
            $starts->utc(),
            $ends->utc(),
            $acceptance->getKey(),
            'webhook-failed-001',
        );
        $payment = $checkout['payment'];

        app(PaymentWebhookProcessor::class)->process('mock', [
            'event_id' => 'evt-failed-001',
            'gateway_transaction_id' => $payment->gateway_transaction_id,
            'status' => Payment::STATUS_FAILED,
            'paid_at' => null,
            'gateway_fee_amount' => 0,
            'amount' => $payment->amount,
        ]);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->getKey(),
            'status' => Payment::STATUS_FAILED,
        ]);
        $this->assertDatabaseHas('bookings', [
            'id' => $checkout['booking']->getKey(),
            'status' => Booking::STATUS_EXPIRED,
        ]);
        $this->assertDatabaseHas('booking_holds', [
            'id' => $checkout['hold']->getKey(),
            'status' => BookingHold::STATUS_RELEASED,
        ]);
    }

    private function createCheckoutContext(): array
    {
        $owner = User::factory()->create([
            'role' => User::ROLE_OWNER,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $customer = User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $business = $owner->businesses()->create([
            'name' => 'Usaha Webhook Uji',
            'slug' => 'usaha-webhook-' . strtolower((string) Str::ulid()),
            'address' => 'Jalan Contoh Nomor 10',
            'timezone' => 'Asia/Jakarta',
            'status' => Business::STATUS_ACTIVE,
            'published_at' => now(),
        ]);
        $plan = SubscriptionPlan::query()->create([
            'slug' => 'plan-webhook-' . strtolower((string) Str::ulid()),
            'name' => 'Plan Webhook Uji',
            'monthly_price' => 100000,
            'duration_days' => 30,
            'max_businesses' => 2,
            'max_venues_per_business' => 5,
            'has_combined_reporting' => false,
            'is_active' => true,
        ]);
        $now = CarbonImmutable::now('UTC');
        Subscription::query()->create([
            'owner_id' => $owner->getKey(),
            'plan_id' => $plan->getKey(),
            'is_trial' => false,
            'status' => Subscription::STATUS_ACTIVE,
            'starts_at' => $now->subDay(),
            'ends_at' => $now->addDays(30),
            'grace_ends_at' => $now->addDays(37),
        ]);
        $category = Category::query()->firstOrFail();
        $venue = $business->venues()->create([
            'category_id' => $category->getKey(),
            'name' => 'Venue Webhook Uji',
            'slug' => 'venue-webhook-' . strtolower((string) Str::ulid()),
            'status' => Venue::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        for ($day = 0; $day <= 6; $day++) {
            VenueAvailabilityRule::query()->create([
                'venue_id' => $venue->getKey(),
                'day_of_week' => $day,
                'is_open' => true,
                'open_time' => '08:00:00',
                'close_time' => '20:00:00',
                'buffer_minutes' => 0,
            ]);
        }

        $venue->prices()->create([
            'pricing_unit' => 'hourly',
            'step_interval_minutes' => 60,
            'min_duration_units' => 1,
            'max_duration_units' => 8,
            'price_amount' => 100000,
            'is_active' => true,
        ]);
        $document = LegalDocument::query()->create([
            'type' => 'booking_terms',
            'version' => 'v1',
            'title' => 'Syarat Booking',
            'content' => 'Syarat booking untuk pengujian.',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);
        $acceptance = TermsAcceptance::query()->create([
            'user_id' => $customer->getKey(),
            'legal_document_id' => $document->getKey(),
            'context' => 'booking',
            'accepted_at' => now(),
        ]);

        return [$customer, $venue, $acceptance];
    }

    private function futureRange(): array
    {
        $start = CarbonImmutable::now('Asia/Jakarta')->addDays(10)->setTime(10, 0);

        return [$start, $start->addHour()];
    }
}
