<?php

declare(strict_types=1);

use App\Enums\DayType;
use App\Enums\SlotStatus;
use App\Enums\SportType;
use App\Enums\SubscriptionStatus;
use App\Models\Court;
use App\Models\CourtPricingRule;
use App\Models\CourtSlot;
use App\Models\Venue;
use App\Services\PricingResolutionService;
use App\Services\SlotPricingPropagationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow('2026-11-10 12:00:00'); // Tuesday 12:00

    $this->venue = Venue::create([
        'name' => 'Senayan Sports',
        'slug' => 'senayan-sports',
        'city' => 'Jakarta Pusat',
        'address' => 'Jl. Asia Afrika',
        'booking_horizon_days' => 30,
        'allow_reschedule' => true,
        'is_active' => true,
        'subscription_status' => SubscriptionStatus::Active,
    ]);

    $this->court = Court::create([
        'venue_id' => $this->venue->id,
        'name' => 'Court 1',
        'sport_type' => SportType::Badminton,
        'opening_time' => '07:00:00',
        'closing_time' => '23:00:00',
        'default_hourly_rate' => 100000,
        'is_active' => true,
    ]);

    $this->pricingService = new PricingResolutionService;
    $this->propagationService = new SlotPricingPropagationService($this->pricingService);
});

afterEach(function () {
    Carbon::setTestNow();
});

test('pricing propagation updates future available slots and leaves held booked blocked and past slots untouched', function () {
    // 1. Past slot (earlier today at 08:00): available, price 100k
    $pastSlot = CourtSlot::create([
        'court_id' => $this->court->id,
        'date' => '2026-11-10',
        'start_time' => '08:00:00',
        'end_time' => '09:00:00',
        'price' => 100000,
        'status' => SlotStatus::Available,
    ]);

    // 2. Future slot today at 18:00: available, price 100k
    $futureTodayAvailable = CourtSlot::create([
        'court_id' => $this->court->id,
        'date' => '2026-11-10',
        'start_time' => '18:00:00',
        'end_time' => '19:00:00',
        'price' => 100000,
        'status' => SlotStatus::Available,
    ]);

    // 3. Future slot tomorrow at 18:00: available, price 100k
    $futureTomorrowAvailable = CourtSlot::create([
        'court_id' => $this->court->id,
        'date' => '2026-11-11',
        'start_time' => '18:00:00',
        'end_time' => '19:00:00',
        'price' => 100000,
        'status' => SlotStatus::Available,
    ]);

    // 4. Future slot tomorrow: HELD at 100k
    $futureHeld = CourtSlot::create([
        'court_id' => $this->court->id,
        'date' => '2026-11-11',
        'start_time' => '19:00:00',
        'end_time' => '20:00:00',
        'price' => 100000,
        'status' => SlotStatus::Held,
        'locked_until' => now()->addMinutes(10),
    ]);

    // 5. Future slot tomorrow: BOOKED at 100k
    $futureBooked = CourtSlot::create([
        'court_id' => $this->court->id,
        'date' => '2026-11-11',
        'start_time' => '20:00:00',
        'end_time' => '21:00:00',
        'price' => 100000,
        'status' => SlotStatus::Booked,
    ]);

    // 6. Future slot tomorrow: BLOCKED at 100k
    $futureBlocked = CourtSlot::create([
        'court_id' => $this->court->id,
        'date' => '2026-11-11',
        'start_time' => '21:00:00',
        'end_time' => '22:00:00',
        'price' => 100000,
        'status' => SlotStatus::Blocked,
        'block_reason' => 'Maintenance',
    ]);

    // Now operator adds a new Peak rule for Weekdays 17:00-23:00 at 180,000 IDR
    CourtPricingRule::create([
        'court_id' => $this->court->id,
        'day_type' => DayType::Weekday,
        'start_time' => '17:00:00',
        'end_time' => '23:00:00',
        'price_per_hour' => 180000,
        'tier_name' => 'Peak Hours',
    ]);

    // Run propagation
    $updatedCount = $this->propagationService->propagatePricingRules($this->court);

    // Exactly 2 slots should have updated: futureTodayAvailable and futureTomorrowAvailable
    expect($updatedCount)->toBe(2);

    // 1. Past slot remains 100k
    expect($pastSlot->fresh()->price)->toBe(100000);

    // 2. Future available slot today updated to 180k
    expect($futureTodayAvailable->fresh()->price)->toBe(180000);

    // 3. Future available slot tomorrow updated to 180k
    expect($futureTomorrowAvailable->fresh()->price)->toBe(180000);

    // 4. Held slot remains 100k
    expect($futureHeld->fresh()->price)->toBe(100000);

    // 5. Booked slot remains 100k
    expect($futureBooked->fresh()->price)->toBe(100000);

    // 6. Blocked slot remains 100k
    expect($futureBlocked->fresh()->price)->toBe(100000);
});
