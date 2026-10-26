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
use App\Models\VenuePromotion;
use App\Services\Booking\BookingEngineService;
use Carbon\CarbonImmutable;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BookingEngineServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CategorySeeder::class);
    }

    public function test_paid_checkout_creates_pending_booking_payment_and_fifteen_minute_hold(): void
    {
        [$owner, $customer, $business, $venue] = $this->createVenueAndSubscription();
        $this->createHourlyPrice($venue);
        $acceptance = $this->createBookingTermsAcceptance($customer);
        [$starts, $ends] = $this->futureRange();

        $result = app(BookingEngineService::class)->createCheckout(
            $customer,
            $venue,
            $starts->utc(),
            $ends->utc(),
            $acceptance->getKey(),
            'checkout-paid-001',
        );

        $this->assertSame(Booking::STATUS_PENDING_PAYMENT, $result['booking']->status);
        $this->assertTrue($result['booking']->payment_required);
        $this->assertSame(Payment::STATUS_PENDING, $result['payment']->status);
        $this->assertNotNull($result['payment']->qris_payload);
        $this->assertSame('mock', $result['payment']->gateway_provider);
        $this->assertStringStartsWith('MOCK-QRIS:', $result['payment']->qris_payload);
        $this->assertSame(100000, $result['payment']->amount);
        $this->assertSame(BookingHold::STATUS_ACTIVE, $result['hold']->status);
        $this->assertEqualsWithDelta(now('UTC')->addMinutes(15)->timestamp, $result['hold']->expires_at->timestamp, 5);
        $this->assertSame($acceptance->getKey(), $result['booking']->terms_acceptance_id);
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_zero_amount_checkout_confirms_without_creating_payment_and_consumes_promo_quota(): void
    {
        [$owner, $customer, $business, $venue] = $this->createVenueAndSubscription();
        $this->createHourlyPrice($venue);
        $promotion = $venue->promotions()->create([
            'business_id' => null,
            'code' => null,
            'name' => 'Diskon penuh',
            'funded_by' => 'owner',
            'discount_type' => 'percentage',
            'discount_value' => 100,
            'max_discount_amount' => 100000,
            'is_combinable_with_owner_promo' => false,
            'max_quota' => 1,
            'used_count' => 0,
            'starts_at' => now('UTC')->subHour(),
            'ends_at' => now('UTC')->addDays(30),
            'status' => 'active',
            'created_by' => $owner->getKey(),
        ]);
        $acceptance = $this->createBookingTermsAcceptance($customer);
        [$starts, $ends] = $this->futureRange();

        $result = app(BookingEngineService::class)->createCheckout(
            $customer,
            $venue,
            $starts->utc(),
            $ends->utc(),
            $acceptance->getKey(),
            'checkout-free-001',
        );

        $this->assertSame(Booking::STATUS_CONFIRMED, $result['booking']->status);
        $this->assertFalse($result['booking']->payment_required);
        $this->assertNull($result['payment']);
        $this->assertSame(BookingHold::STATUS_CONVERTED, $result['hold']->status);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseHas('venue_promotions', [
            'id' => $promotion->getKey(),
            'used_count' => 1,
        ]);
    }

    public function test_repeating_the_same_idempotency_key_returns_the_original_checkout(): void
    {
        [$owner, $customer, $business, $venue] = $this->createVenueAndSubscription();
        $this->createHourlyPrice($venue);
        $acceptance = $this->createBookingTermsAcceptance($customer);
        [$starts, $ends] = $this->futureRange();
        $engine = app(BookingEngineService::class);

        $first = $engine->createCheckout($customer, $venue, $starts->utc(), $ends->utc(), $acceptance->getKey(), 'checkout-replay-001');
        $second = $engine->createCheckout($customer, $venue, $starts->utc(), $ends->utc(), $acceptance->getKey(), 'checkout-replay-001');

        $this->assertFalse($first['idempotent_replay']);
        $this->assertTrue($second['idempotent_replay']);
        $this->assertSame($first['booking']->getKey(), $second['booking']->getKey());
        $this->assertSame($first['hold']->getKey(), $second['hold']->getKey());
        $this->assertSame($first['payment']->getKey(), $second['payment']->getKey());
        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseCount('booking_holds', 1);
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_same_idempotency_key_cannot_be_reused_for_a_different_range(): void
    {
        [$owner, $customer, $business, $venue] = $this->createVenueAndSubscription();
        $this->createHourlyPrice($venue);
        $acceptance = $this->createBookingTermsAcceptance($customer);
        [$starts, $ends] = $this->futureRange();
        $engine = app(BookingEngineService::class);

        $engine->createCheckout($customer, $venue, $starts->utc(), $ends->utc(), $acceptance->getKey(), 'checkout-conflict-001');

        try {
            $engine->createCheckout(
                $customer,
                $venue,
                $starts->addHours(3)->utc(),
                $ends->addHours(3)->utc(),
                $acceptance->getKey(),
                'checkout-conflict-001',
            );
            $this->fail('Expected idempotency key reuse to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('idempotencyKey', $exception->errors());
        }

        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_paid_checkout_rejects_customer_without_booking_terms_acceptance(): void
    {
        [$owner, $customer, $business, $venue] = $this->createVenueAndSubscription();
        $this->createHourlyPrice($venue);
        [$starts, $ends] = $this->futureRange();

        $this->expectException(ValidationException::class);

        app(BookingEngineService::class)->createCheckout(
            $customer,
            $venue,
            $starts->utc(),
            $ends->utc(),
            999999,
            'checkout-no-terms-001',
        );
    }

    private function createVenueAndSubscription(): array
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
            'name' => 'Usaha Booking Uji',
            'slug' => 'usaha-booking-' . strtolower((string) Str::ulid()),
            'address' => 'Jalan Contoh Nomor 10',
            'timezone' => 'Asia/Jakarta',
            'status' => Business::STATUS_ACTIVE,
            'published_at' => now(),
        ]);
        $plan = SubscriptionPlan::query()->create([
            'slug' => 'plan-booking-' . strtolower((string) Str::ulid()),
            'name' => 'Plan Booking Uji',
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
            'name' => 'Venue Booking Uji',
            'slug' => 'venue-booking-' . strtolower((string) Str::ulid()),
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

        return [$owner, $customer, $business, $venue];
    }

    private function createHourlyPrice(Venue $venue): VenuePrice
    {
        return $venue->prices()->create([
            'pricing_unit' => 'hourly',
            'step_interval_minutes' => 60,
            'min_duration_units' => 1,
            'max_duration_units' => 8,
            'price_amount' => 100000,
            'is_active' => true,
        ]);
    }

    private function createBookingTermsAcceptance(User $customer): TermsAcceptance
    {
        $document = LegalDocument::query()->create([
            'type' => 'booking_terms',
            'version' => 'v1',
            'title' => 'Syarat Booking',
            'content' => 'Syarat booking untuk pengujian.',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);

        return TermsAcceptance::query()->create([
            'user_id' => $customer->getKey(),
            'legal_document_id' => $document->getKey(),
            'context' => 'booking',
            'accepted_at' => now(),
        ]);
    }

    private function futureRange(): array
    {
        $start = CarbonImmutable::now('Asia/Jakarta')->addDays(10)->setTime(10, 0);

        return [$start, $start->addHours(1)];
    }
}
