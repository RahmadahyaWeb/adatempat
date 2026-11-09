<?php

declare(strict_types=1);

use App\Enums\DayType;
use App\Enums\SportType;
use App\Enums\SubscriptionStatus;
use App\Models\Court;
use App\Models\CourtPricingRule;
use App\Models\Venue;
use App\Services\PricingResolutionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->venue = Venue::create([
        'name' => 'Elite Arena',
        'slug' => 'elite-arena',
        'city' => 'Jakarta Selatan',
        'address' => 'Jl. Kemang Raya',
        'booking_horizon_days' => 30,
        'allow_reschedule' => true,
        'is_active' => true,
        'subscription_status' => SubscriptionStatus::Active,
    ]);

    $this->court = Court::create([
        'venue_id' => $this->venue->id,
        'name' => 'Court 1',
        'sport_type' => SportType::Padel,
        'opening_time' => '06:00:00',
        'closing_time' => '23:00:00',
        'default_hourly_rate' => 100000, // 100k fallback
        'is_active' => true,
    ]);

    $this->service = new PricingResolutionService;
});

test('pricing resolution falls back to court default hourly rate when no rules exist', function () {
    $weekday = Carbon::parse('2026-11-09'); // Monday
    $price = $this->service->resolveSlotPrice($this->court, $weekday, '08:00:00', '09:00:00');

    expect($price)->toBe(100000);
});

test('pricing resolution matches weekday regular and peak rules correctly', function () {
    CourtPricingRule::create([
        'court_id' => $this->court->id,
        'day_type' => DayType::Weekday,
        'start_time' => '06:00:00',
        'end_time' => '17:00:00',
        'price_per_hour' => 120000,
        'tier_name' => 'Weekday Regular',
    ]);

    CourtPricingRule::create([
        'court_id' => $this->court->id,
        'day_type' => DayType::Weekday,
        'start_time' => '17:00:00',
        'end_time' => '23:00:00',
        'price_per_hour' => 200000,
        'tier_name' => 'Weekday Peak',
    ]);

    $monday = Carbon::parse('2026-11-09');

    // 10:00 - 11:00 should match regular
    expect($this->service->resolveSlotPrice($this->court, $monday, '10:00:00', '11:00:00'))->toBe(120000);

    // 19:00 - 20:00 should match peak
    expect($this->service->resolveSlotPrice($this->court, $monday, '19:00:00', '20:00:00'))->toBe(200000);

    // Weekend date without weekend rules falls back to court default rate
    $saturday = Carbon::parse('2026-11-14');
    expect($this->service->resolveSlotPrice($this->court, $saturday, '10:00:00', '11:00:00'))->toBe(100000);
});

test('pricing resolution matches weekend pricing rule on weekends', function () {
    CourtPricingRule::create([
        'court_id' => $this->court->id,
        'day_type' => DayType::Weekend,
        'start_time' => '06:00:00',
        'end_time' => '23:00:00',
        'price_per_hour' => 250000,
        'tier_name' => 'Weekend All-day',
    ]);

    $sunday = Carbon::parse('2026-11-15');

    expect($this->service->resolveSlotPrice($this->court, $sunday, '14:00:00', '15:00:00'))->toBe(250000);
});

test('pricing resolution works with in-memory relations without additional queries', function () {
    CourtPricingRule::create([
        'court_id' => $this->court->id,
        'day_type' => DayType::Weekday,
        'start_time' => '07:00:00',
        'end_time' => '12:00:00',
        'price_per_hour' => 110000,
        'tier_name' => 'Morning Special',
    ]);

    $courtWithRelations = Court::with('pricingRules')->find($this->court->id);
    $tuesday = Carbon::parse('2026-11-10');

    expect($this->service->resolveSlotPrice($courtWithRelations, $tuesday, '08:00:00', '09:00:00'))->toBe(110000);
});

test('pricing rule hierarchy resolves narrower specific time windows over broader rules', function () {
    // 1. Broad rule: 06:00 - 23:00 (17 hours) @ 120k
    CourtPricingRule::create([
        'court_id' => $this->court->id,
        'day_type' => DayType::Weekday,
        'start_time' => '06:00:00',
        'end_time' => '23:00:00',
        'price_per_hour' => 120000,
        'tier_name' => 'All Day Base',
    ]);

    // 2. Narrower Peak rule: 17:00 - 22:00 (5 hours) @ 180k
    CourtPricingRule::create([
        'court_id' => $this->court->id,
        'day_type' => DayType::Weekday,
        'start_time' => '17:00:00',
        'end_time' => '22:00:00',
        'price_per_hour' => 180000,
        'tier_name' => 'Evening Peak',
    ]);

    // 3. Narrowest Prime rule: 19:00 - 20:00 (1 hour) @ 220k
    CourtPricingRule::create([
        'court_id' => $this->court->id,
        'day_type' => DayType::Weekday,
        'start_time' => '19:00:00',
        'end_time' => '20:00:00',
        'price_per_hour' => 220000,
        'tier_name' => 'Prime Hour',
    ]);

    $wednesday = Carbon::parse('2026-11-11');

    // 10:00 - 11:00 only matches broad rule (120k)
    expect($this->service->resolveSlotPrice($this->court, $wednesday, '10:00:00', '11:00:00'))->toBe(120000);

    // 17:00 - 18:00 matches broad and peak rules -> peak rule (narrower) wins (180k)
    expect($this->service->resolveSlotPrice($this->court, $wednesday, '17:00:00', '18:00:00'))->toBe(180000);

    // 19:00 - 20:00 matches all three -> prime rule (narrowest) wins (220k)
    expect($this->service->resolveSlotPrice($this->court, $wednesday, '19:00:00', '20:00:00'))->toBe(220000);
});
