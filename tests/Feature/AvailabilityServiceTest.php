<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingHold;
use App\Models\Business;
use App\Models\Category;
use App\Models\LegalDocument;
use App\Models\TermsAcceptance;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueAvailabilityRule;
use App\Models\VenueBlock;
use App\Services\Booking\AvailabilityService;
use Carbon\CarbonImmutable;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AvailabilityServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CategorySeeder::class);
    }

    public function test_it_allows_a_future_range_inside_opening_hours(): void
    {
        [$business, $venue] = $this->createVenue();
        $start = CarbonImmutable::now('Asia/Jakarta')->addDays(3)->setTime(10, 0)->utc();
        $end = $start->addHours(2);

        $this->assertTrue(app(AvailabilityService::class)->isAvailable($venue, $start, $end));
    }

    public function test_it_rejects_a_range_that_overlaps_a_manual_block(): void
    {
        [$business, $venue, $owner] = $this->createVenue();
        $start = CarbonImmutable::now('Asia/Jakarta')->addDays(3)->setTime(10, 0)->utc();
        $end = $start->addHours(2);

        VenueBlock::query()->create([
            'venue_id' => $venue->getKey(),
            'starts_at' => $start->addMinutes(30),
            'ends_at' => $start->addMinutes(90),
            'reason' => 'Perawatan',
            'created_by' => $owner->getKey(),
        ]);

        $this->assertFalse(app(AvailabilityService::class)->isAvailable($venue, $start, $end));
    }

    public function test_it_rejects_an_active_hold_but_ignores_an_expired_hold(): void
    {
        [$business, $venue, $owner, $customer] = $this->createVenue();
        $start = CarbonImmutable::now('Asia/Jakarta')->addDays(3)->setTime(10, 0)->utc();
        $end = $start->addHours(2);

        BookingHold::query()->create([
            'venue_id' => $venue->getKey(),
            'customer_id' => $customer->getKey(),
            'starts_at' => $start,
            'ends_at' => $end,
            'expires_at' => now('UTC')->addMinutes(10),
            'idempotency_key' => (string) Str::ulid(),
            'status' => BookingHold::STATUS_ACTIVE,
        ]);

        $this->assertFalse(app(AvailabilityService::class)->isAvailable($venue, $start, $end));

        BookingHold::query()->where('venue_id', $venue->getKey())->update([
            'expires_at' => now('UTC')->subMinute(),
        ]);

        $this->assertTrue(app(AvailabilityService::class)->isAvailable($venue, $start, $end));
    }

    public function test_it_supports_an_operating_window_that_crosses_midnight(): void
    {
        [$business, $venue] = $this->createVenue('Asia/Jakarta', false);
        $monday = CarbonImmutable::now('Asia/Jakarta')->next(1)->setTime(22, 0);
        $tuesday = $monday->addDay()->setTime(1, 0);

        VenueAvailabilityRule::query()->create([
            'venue_id' => $venue->getKey(),
            'day_of_week' => 1,
            'is_open' => true,
            'open_time' => '18:00:00',
            'close_time' => '02:00:00',
            'buffer_minutes' => 0,
        ]);

        $this->assertTrue(app(AvailabilityService::class)->isAvailable($venue, $monday->utc(), $tuesday->utc()));
    }

    public function test_it_rejects_an_overlapping_booking_that_still_blocks_the_slot(): void
    {
        [$business, $venue, $owner, $customer] = $this->createVenue();
        $start = CarbonImmutable::now('Asia/Jakarta')->addDays(3)->setTime(10, 0)->utc();
        $end = $start->addHours(2);
        $legalDocument = LegalDocument::query()->create([
            'type' => 'terms_and_conditions',
            'version' => 'test-v1',
            'title' => 'Terms test',
            'content' => 'Test terms',
            'status' => 'published',
            'published_at' => now(),
        ]);
        $acceptance = TermsAcceptance::query()->create([
            'user_id' => $customer->getKey(),
            'legal_document_id' => $legalDocument->getKey(),
            'context' => 'booking',
            'accepted_at' => now(),
        ]);

        Booking::query()->create([
            'reference_code' => 'ADT-' . strtoupper(Str::random(12)),
            'venue_id' => $venue->getKey(),
            'business_id' => $business->getKey(),
            'customer_id' => $customer->getKey(),
            'pricing_unit' => 'hourly',
            'duration_units' => 2,
            'booking_start' => $start,
            'booking_end' => $end,
            'payment_required' => true,
            'status' => Booking::STATUS_CONFIRMED,
            'snapshot_base_amount' => 200000,
            'snapshot_owner_discount_amount' => 0,
            'snapshot_platform_subsidy_amount' => 0,
            'snapshot_actual_paid_amount' => 200000,
            'snapshot_gateway_fee_amount' => 0,
            'snapshot_net_owner_entitlement' => 200000,
            'snapshot_refund_policy_json' => ['customer_cancellation_percent' => 0],
            'terms_acceptance_id' => $acceptance->getKey(),
            'confirmed_at' => now(),
        ]);

        $this->assertFalse(app(AvailabilityService::class)->isAvailable($venue, $start, $end));
    }

    private function createVenue(string $timezone = 'Asia/Jakarta', bool $withDefaultHours = true): array
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
            'name' => 'Usaha Availability Uji',
            'slug' => 'usaha-availability-' . strtolower((string) Str::ulid()),
            'address' => 'Jalan Contoh Nomor 10',
            'timezone' => $timezone,
            'status' => Business::STATUS_ACTIVE,
            'published_at' => now(),
        ]);
        $category = Category::query()->firstOrFail();
        $venue = $business->venues()->create([
            'category_id' => $category->getKey(),
            'name' => 'Venue Availability Uji',
            'slug' => 'venue-availability-' . strtolower((string) Str::ulid()),
            'status' => Venue::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        if ($withDefaultHours) {
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
        }

        return [$business, $venue, $owner, $customer];
    }
}
