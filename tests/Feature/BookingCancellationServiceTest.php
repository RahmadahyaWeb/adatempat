<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingHold;
use App\Models\Business;
use App\Models\Category;
use App\Models\LegalDocument;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\TermsAcceptance;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueAvailabilityRule;
use App\Models\VenuePrice;
use App\Services\Booking\BookingCancellationService;
use App\Services\Booking\BookingEngineService;
use App\Services\Payments\PaymentWebhookProcessor;
use App\Services\Payments\RefundService;
use App\Services\Payments\RefundWebhookProcessor;
use App\Services\Payments\RefundReconciliationService;
use Carbon\CarbonImmutable;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BookingCancellationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CategorySeeder::class);
    }

    public function test_customer_cancellation_keeps_slot_blocked_until_refund_succeeds(): void
    {
        [$owner, $customer, $booking] = $this->createConfirmedBooking('cancel-customer-001');

        $result = app(BookingCancellationService::class)->cancelByCustomer($customer, $booking, 'Perubahan rencana');

        $this->assertSame(Booking::STATUS_CANCELLED_BY_CUSTOMER, $result['booking']->status);
        $this->assertSame(100000, $result['refund_amount']);
        $this->assertSame('processing', $result['refund']->status);
        $this->assertNull($result['booking']->slot_released_at);

        app(RefundService::class)->markSucceeded($result['refund'], 'MANUAL-MOCK-REFUND-1');

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->getKey(),
            'status' => Booking::STATUS_CANCELLED_BY_CUSTOMER,
        ]);
        $this->assertNotNull($booking->fresh()->slot_released_at);
        $this->assertDatabaseHas('payments', [
            'payable_id' => $booking->getKey(),
            'status' => Payment::STATUS_REFUNDED,
        ]);
    }

    public function test_owner_cancellation_releases_slot_immediately_and_requests_full_refund(): void
    {
        [$owner, $customer, $booking] = $this->createConfirmedBooking('cancel-owner-001');

        $result = app(BookingCancellationService::class)->cancelByOwner($owner, $booking, 'Venue mengalami kerusakan.');

        $this->assertSame(Booking::STATUS_CANCELLED_BY_OWNER, $result['booking']->status);
        $this->assertSame(100000, $result['refund_amount']);
        $this->assertSame('processing', $result['refund']->status);
        $this->assertNotNull($result['booking']->slot_released_at);
    }

    public function test_successful_refund_webhook_releases_customer_cancelled_slot(): void
    {
        [$owner, $customer, $booking] = $this->createConfirmedBooking('refund-webhook-success-001');
        $cancelled = app(BookingCancellationService::class)->cancelByCustomer($customer, $booking);
        $refund = $cancelled['refund'];

        $result = app(RefundWebhookProcessor::class)->process('mock', [
            'event_id' => 'evt-refund-success-001',
            'gateway_refund_id' => $refund->gateway_refund_id,
            'status' => 'succeeded',
        ]);

        $this->assertSame('processed', $result['status']);
        $this->assertDatabaseHas('refunds', [
            'id' => $refund->getKey(),
            'status' => 'succeeded',
        ]);
        $this->assertNotNull($booking->fresh()->slot_released_at);
        $this->assertDatabaseHas('payments', [
            'payable_id' => $booking->getKey(),
            'status' => Payment::STATUS_REFUNDED,
        ]);
    }

    public function test_failed_refund_webhook_keeps_customer_cancelled_slot_blocked(): void
    {
        [$owner, $customer, $booking] = $this->createConfirmedBooking('refund-webhook-failed-001');
        $cancelled = app(BookingCancellationService::class)->cancelByCustomer($customer, $booking);
        $refund = $cancelled['refund'];

        app(RefundWebhookProcessor::class)->process('mock', [
            'event_id' => 'evt-refund-failed-001',
            'gateway_refund_id' => $refund->gateway_refund_id,
            'status' => 'failed',
        ]);

        $this->assertDatabaseHas('refunds', [
            'id' => $refund->getKey(),
            'status' => 'failed',
        ]);
        $this->assertNull($booking->fresh()->slot_released_at);
    }

    public function test_duplicate_refund_webhook_does_not_apply_refund_twice(): void
    {
        [$owner, $customer, $booking] = $this->createConfirmedBooking('refund-webhook-duplicate-001');
        $cancelled = app(BookingCancellationService::class)->cancelByCustomer($customer, $booking);
        $refund = $cancelled['refund'];
        $event = [
            'event_id' => 'evt-refund-duplicate-001',
            'gateway_refund_id' => $refund->gateway_refund_id,
            'status' => 'succeeded',
        ];

        app(RefundWebhookProcessor::class)->process('mock', $event);
        $second = app(RefundWebhookProcessor::class)->process('mock', $event);

        $this->assertTrue($second['duplicate']);
        $this->assertDatabaseHas('payment_webhook_events', [
            'gateway_provider' => 'mock',
            'gateway_event_id' => 'evt-refund-duplicate-001',
            'duplicate_delivery_count' => 1,
        ]);
        $this->assertSame(1, \App\Models\Refund::query()->where('payment_id', $refund->payment_id)->where('status', 'succeeded')->count());
    }

    public function test_refund_reconciliation_retries_failed_refund_with_same_idempotency_key(): void
    {
        [$owner, $customer, $booking] = $this->createConfirmedBooking('refund-retry-001');
        $cancelled = app(BookingCancellationService::class)->cancelByCustomer($customer, $booking);
        $refund = $cancelled['refund'];
        $refund->update(['status' => 'failed']);

        $result = app(RefundReconciliationService::class)->reconcile();

        $this->assertSame(1, $result['scanned']);
        $this->assertSame(1, $result['processing']);
        $this->assertDatabaseHas('refunds', [
            'id' => $refund->getKey(),
            'status' => 'processing',
            'idempotency_key' => $refund->idempotency_key,
        ]);
        $this->assertNull($booking->fresh()->slot_released_at);
    }

    public function test_customer_cancellation_with_zero_percent_refund_releases_slot_without_refund(): void
    {
        [$owner, $customer, $booking] = $this->createConfirmedBooking('cancel-zero-refund-001');
        $booking->update([
            'snapshot_refund_policy_json' => [
                'source' => 'venue_policy',
                'customer_cancellation' => ['refund_percentage' => 0],
            ],
        ]);

        $result = app(BookingCancellationService::class)->cancelByCustomer($customer, $booking);

        $this->assertSame(0, $result['refund_amount']);
        $this->assertNull($result['refund']);
        $this->assertNotNull($result['booking']->slot_released_at);
        $this->assertDatabaseCount('refunds', 0);
    }

    public function test_customer_cannot_cancel_with_less_than_sixty_minutes_notice(): void
    {
        [$owner, $customer, $booking] = $this->createConfirmedBooking('cancel-cutoff-001');
        $booking->update([
            'booking_start' => now('UTC')->addMinutes(30),
            'booking_end' => now('UTC')->addMinutes(90),
        ]);

        $this->expectException(ValidationException::class);

        app(BookingCancellationService::class)->cancelByCustomer($customer, $booking);
    }

    private function createConfirmedBooking(string $idempotencyKey): array
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
            'name' => 'Usaha Cancel Uji',
            'slug' => 'usaha-cancel-' . strtolower((string) Str::ulid()),
            'address' => 'Jalan Contoh Nomor 10',
            'timezone' => 'Asia/Jakarta',
            'status' => Business::STATUS_ACTIVE,
            'published_at' => now(),
        ]);
        $plan = SubscriptionPlan::query()->create([
            'slug' => 'plan-cancel-' . strtolower((string) Str::ulid()),
            'name' => 'Plan Cancel Uji',
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
            'name' => 'Venue Cancel Uji',
            'slug' => 'venue-cancel-' . strtolower((string) Str::ulid()),
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
        $starts = CarbonImmutable::now('Asia/Jakarta')->addDays(10)->setTime(10, 0);
        $ends = $starts->addHour();
        $checkout = app(BookingEngineService::class)->createCheckout(
            $customer,
            $venue,
            $starts->utc(),
            $ends->utc(),
            $acceptance->getKey(),
            $idempotencyKey,
        );
        app(PaymentWebhookProcessor::class)->process('mock', [
            'event_id' => 'evt-' . $idempotencyKey,
            'gateway_transaction_id' => $checkout['payment']->gateway_transaction_id,
            'status' => Payment::STATUS_PAID,
            'paid_at' => CarbonImmutable::now('UTC')->toIso8601String(),
            'gateway_fee_amount' => 0,
            'amount' => $checkout['payment']->amount,
        ]);

        return [$owner, $customer, $checkout['booking']->fresh()];
    }
}
