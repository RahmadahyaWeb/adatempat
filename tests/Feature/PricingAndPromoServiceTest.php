<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Category;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenuePrice;
use App\Models\VenuePromotion;
use App\Services\Booking\PricingAndPromoService;
use Carbon\CarbonImmutable;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PricingAndPromoServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CategorySeeder::class);
    }

    public function test_hourly_quote_selects_the_largest_owner_discount_and_a_combinable_platform_subsidy(): void
    {
        [$owner, $business, $venue] = $this->createVenue();
        $price = $venue->prices()->create([
            'pricing_unit' => 'hourly',
            'step_interval_minutes' => 60,
            'min_duration_units' => 1,
            'max_duration_units' => 8,
            'price_amount' => 100000,
            'weekend_price_amount' => null,
            'is_active' => true,
        ]);

        $starts = CarbonImmutable::now('Asia/Jakarta')->addDays(10)->setTime(10, 0);
        $ends = $starts->addHours(2);
        $firstOwnerPromo = $this->createPromotion($owner, $venue, [
            'name' => 'Owner 10 persen',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'max_discount_amount' => 50000,
        ]);
        $bestOwnerPromo = $this->createPromotion($owner, $venue, [
            'name' => 'Owner nominal 25 ribu',
            'discount_type' => 'fixed_amount',
            'discount_value' => 25000,
            'max_discount_amount' => null,
        ]);
        $platformPromo = $this->createPromotion($owner, $venue, [
            'venue_id' => null,
            'business_id' => null,
            'funded_by' => 'platform',
            'name' => 'Subsidi platform',
            'discount_type' => 'percentage',
            'discount_value' => 50,
            'max_discount_amount' => 100000,
            'is_combinable_with_owner_promo' => true,
        ]);

        $quote = app(PricingAndPromoService::class)->quote(
            $venue,
            $starts->utc(),
            $ends->utc(),
        );

        $this->assertSame('hourly', $quote['pricing_unit']);
        $this->assertSame(2, $quote['duration_units']);
        $this->assertSame(200000, $quote['base_amount']);
        $this->assertSame($bestOwnerPromo->getKey(), $quote['owner_promotion_id']);
        $this->assertSame(25000, $quote['owner_discount_amount']);
        $this->assertSame($platformPromo->getKey(), $quote['platform_promotion_id']);
        $this->assertSame(87500, $quote['platform_subsidy_amount']);
        $this->assertSame(87500, $quote['actual_paid_amount']);
        $this->assertSame(175000, $quote['owner_entitlement_before_gateway_fee']);
        $this->assertEqualsCanonicalizing(
            [$bestOwnerPromo->getKey(), $platformPromo->getKey()],
            $quote['promotion_quota_to_consume'],
        );
        $this->assertDatabaseHas('venue_promotions', [
            'id' => $firstOwnerPromo->getKey(),
            'used_count' => 0,
        ]);
        $this->assertDatabaseHas('venue_promotions', [
            'id' => $bestOwnerPromo->getKey(),
            'used_count' => 0,
        ]);
        $this->assertDatabaseHas('venue_promotions', [
            'id' => $platformPromo->getKey(),
            'used_count' => 0,
        ]);
    }

    public function test_owner_promotion_tie_break_uses_the_smallest_id(): void
    {
        [$owner, $business, $venue] = $this->createVenue();
        $venue->prices()->create([
            'pricing_unit' => 'hourly',
            'step_interval_minutes' => 60,
            'min_duration_units' => 1,
            'max_duration_units' => 4,
            'price_amount' => 50000,
            'is_active' => true,
        ]);

        $older = $this->createPromotion($owner, $venue, [
            'name' => 'Promo pertama',
            'discount_type' => 'fixed_amount',
            'discount_value' => 10000,
            'max_discount_amount' => null,
        ]);
        $this->createPromotion($owner, $venue, [
            'name' => 'Promo kedua',
            'discount_type' => 'fixed_amount',
            'discount_value' => 10000,
            'max_discount_amount' => null,
        ]);

        $starts = CarbonImmutable::now('Asia/Jakarta')->addDays(10)->setTime(10, 0);
        $quote = app(PricingAndPromoService::class)->quote(
            $venue,
            $starts->utc(),
            $starts->addHour()->utc(),
        );

        $this->assertSame($older->getKey(), $quote['owner_promotion_id']);
        $this->assertSame(10000, $quote['owner_discount_amount']);
        $this->assertSame(40000, $quote['actual_paid_amount']);
    }

    public function test_non_combinable_platform_promotion_is_not_applied_with_an_owner_promotion(): void
    {
        [$owner, $business, $venue] = $this->createVenue();
        $venue->prices()->create([
            'pricing_unit' => 'hourly',
            'step_interval_minutes' => 60,
            'min_duration_units' => 1,
            'max_duration_units' => 4,
            'price_amount' => 100000,
            'is_active' => true,
        ]);

        $ownerPromo = $this->createPromotion($owner, $venue, [
            'discount_type' => 'fixed_amount',
            'discount_value' => 10000,
            'max_discount_amount' => null,
        ]);
        $this->createPromotion($owner, $venue, [
            'venue_id' => null,
            'business_id' => null,
            'funded_by' => 'platform',
            'discount_type' => 'fixed_amount',
            'discount_value' => 20000,
            'max_discount_amount' => null,
            'is_combinable_with_owner_promo' => false,
        ]);

        $starts = CarbonImmutable::now('Asia/Jakarta')->addDays(10)->setTime(10, 0);
        $quote = app(PricingAndPromoService::class)->quote(
            $venue,
            $starts->utc(),
            $starts->addHour()->utc(),
        );

        $this->assertSame($ownerPromo->getKey(), $quote['owner_promotion_id']);
        $this->assertNull($quote['platform_promotion_id']);
        $this->assertSame(0, $quote['platform_subsidy_amount']);
        $this->assertSame(90000, $quote['actual_paid_amount']);
    }

    public function test_daily_quote_applies_weekend_price_per_calendar_day(): void
    {
        [$owner, $business, $venue] = $this->createVenue();
        $venue->prices()->create([
            'pricing_unit' => 'daily',
            'step_interval_minutes' => 60,
            'min_duration_units' => 1,
            'max_duration_units' => 10,
            'price_amount' => 100000,
            'weekend_price_amount' => 150000,
            'is_active' => true,
        ]);

        $start = CarbonImmutable::now('Asia/Jakarta')->next(CarbonImmutable::FRIDAY)->startOfDay();
        $end = $start->addDays(3);
        $quote = app(PricingAndPromoService::class)->quote($venue, $start->utc(), $end->utc());

        $this->assertSame(3, $quote['duration_units']);
        $this->assertSame(400000, $quote['base_amount']);
    }

    public function test_hourly_quote_rejects_duration_not_aligned_to_the_price_interval(): void
    {
        [$owner, $business, $venue] = $this->createVenue();
        $venue->prices()->create([
            'pricing_unit' => 'hourly',
            'step_interval_minutes' => 60,
            'min_duration_units' => 1,
            'max_duration_units' => 10,
            'price_amount' => 100000,
            'is_active' => true,
        ]);

        $start = CarbonImmutable::now('Asia/Jakarta')->addDays(10)->setTime(10, 0);

        try {
            app(PricingAndPromoService::class)->quote($venue, $start->utc(), $start->addMinutes(90)->utc());
            $this->fail('Expected invalid duration to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('bookingEnd', $exception->errors());
        }
    }

    private function createVenue(): array
    {
        $owner = User::factory()->create([
            'role' => User::ROLE_OWNER,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $business = $owner->businesses()->create([
            'name' => 'Usaha Harga Uji',
            'slug' => 'usaha-harga-' . strtolower((string) Str::ulid()),
            'address' => 'Jalan Contoh Nomor 10',
            'timezone' => 'Asia/Jakarta',
            'status' => Business::STATUS_ACTIVE,
            'published_at' => now(),
        ]);
        $category = Category::query()->firstOrFail();
        $venue = $business->venues()->create([
            'category_id' => $category->getKey(),
            'name' => 'Venue Harga Uji',
            'slug' => 'venue-harga-' . strtolower((string) Str::ulid()),
            'status' => Venue::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        return [$owner, $business, $venue];
    }

    private function createPromotion(User $owner, Venue $venue, array $attributes = []): VenuePromotion
    {
        return VenuePromotion::query()->create(array_merge([
            'venue_id' => $venue->getKey(),
            'business_id' => null,
            'code' => null,
            'name' => 'Promo uji',
            'funded_by' => 'owner',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'max_discount_amount' => 50000,
            'is_combinable_with_owner_promo' => false,
            'max_quota' => 10,
            'used_count' => 0,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(20),
            'status' => 'active',
            'created_by' => $owner->getKey(),
        ], $attributes));
    }
}
