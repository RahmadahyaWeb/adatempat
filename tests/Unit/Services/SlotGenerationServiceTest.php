<?php

declare(strict_types=1);

use App\Enums\DayType;
use App\Enums\SlotStatus;
use App\Enums\SportType;
use App\Enums\SubscriptionStatus;
use App\Exceptions\BookingHorizonExceededException;
use App\Models\Court;
use App\Models\CourtPricingRule;
use App\Models\CourtSlot;
use App\Models\Venue;
use App\Services\PricingResolutionService;
use App\Services\SlotGenerationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow('2026-11-10 10:00:00'); // Tuesday

    $this->venue = Venue::create([
        'name' => 'Senayan Sports',
        'slug' => 'senayan-sports',
        'city' => 'Jakarta Pusat',
        'address' => 'Jl. Asia Afrika',
        'booking_horizon_days' => 14, // 14 days horizon
        'allow_reschedule' => true,
        'is_active' => true,
        'subscription_status' => SubscriptionStatus::Active,
    ]);

    $this->court = Court::create([
        'venue_id' => $this->venue->id,
        'name' => 'Court 1',
        'sport_type' => SportType::Badminton,
        'opening_time' => '07:00:00',
        'closing_time' => '11:00:00', // 4 one-hour blocks: 07-08, 08-09, 09-10, 10-11
        'default_hourly_rate' => 100000,
        'is_active' => true,
    ]);

    $this->pricingService = new PricingResolutionService;
    $this->slotService = new SlotGenerationService($this->pricingService);
});

afterEach(function () {
    Carbon::setTestNow();
});

test('slot generation creates discrete 60-minute blocks strictly aligned on the hour', function () {
    $date = Carbon::parse('2026-11-12'); // Thursday (within horizon)

    $slots = $this->slotService->generateSlotsForCourtAndDate($this->court, $date);

    expect($slots)->toHaveCount(4);

    $intervals = $slots->map(fn (CourtSlot $s) => $s->start_time.' - '.$s->end_time)->all();
    expect($intervals)->toBe([
        '07:00:00 - 08:00:00',
        '08:00:00 - 09:00:00',
        '09:00:00 - 10:00:00',
        '10:00:00 - 11:00:00',
    ]);
});

test('slot generation applies tiered pricing rules accurately', function () {
    CourtPricingRule::create([
        'court_id' => $this->court->id,
        'day_type' => DayType::Weekday,
        'start_time' => '07:00:00',
        'end_time' => '09:00:00',
        'price_per_hour' => 150000,
        'tier_name' => 'Morning Special',
    ]);

    $date = Carbon::parse('2026-11-12'); // Thursday
    $slots = $this->slotService->generateSlotsForCourtAndDate($this->court, $date);

    // 07-08: 150k, 08-09: 150k, 09-10: 100k (default), 10-11: 100k (default)
    expect($slots->firstWhere('start_time', '07:00:00')->price)->toBe(150000)
        ->and($slots->firstWhere('start_time', '08:00:00')->price)->toBe(150000)
        ->and($slots->firstWhere('start_time', '09:00:00')->price)->toBe(100000)
        ->and($slots->firstWhere('start_time', '10:00:00')->price)->toBe(100000);
});

test('slot generation is idempotent and does not overwrite existing booked or held slots', function () {
    $date = Carbon::parse('2026-11-12');

    // Pre-create one booked slot
    $bookedSlot = CourtSlot::create([
        'court_id' => $this->court->id,
        'date' => '2026-11-12',
        'start_time' => '08:00:00',
        'end_time' => '09:00:00',
        'price' => 150000,
        'status' => SlotStatus::Booked,
    ]);

    // Generate slots
    $slots = $this->slotService->generateSlotsForCourtAndDate($this->court, $date);

    expect($slots)->toHaveCount(4);

    // The booked slot remains Booked with original price
    $refreshedBooked = CourtSlot::find($bookedSlot->id);
    expect($refreshedBooked->status)->toBe(SlotStatus::Booked)
        ->and($refreshedBooked->price)->toBe(150000);

    // Total slots in DB must still be 4 (no duplicates)
    expect(CourtSlot::where('court_id', $this->court->id)->where('date', '2026-11-12')->count())->toBe(4);

    // Running again idempotently returns the same 4 slots
    $secondRun = $this->slotService->generateSlotsForCourtAndDate($this->court, $date);
    expect($secondRun)->toHaveCount(4)
        ->and(CourtSlot::where('court_id', $this->court->id)->where('date', '2026-11-12')->count())->toBe(4);
});

test('slot generation beyond venue booking horizon is rejected with exception', function () {
    // Venue horizon is 14 days, today is 2026-11-10
    // 2026-11-25 is 15 days ahead (beyond 14 days)
    $beyondDate = Carbon::parse('2026-11-25');

    expect(function () use ($beyondDate) {
        $this->slotService->generateSlotsForCourtAndDate($this->court, $beyondDate);
    })->toThrow(BookingHorizonExceededException::class);
});

test('slot generation for dates in the past is rejected with exception', function () {
    $pastDate = Carbon::parse('2026-11-08');

    expect(function () use ($pastDate) {
        $this->slotService->generateSlotsForCourtAndDate($this->court, $pastDate);
    })->toThrow(BookingHorizonExceededException::class);
});

test('generateRollingSlots generates discrete slots across horizon days', function () {
    // Horizon is 14 days. Generate for 2 days (day 0 and day 1)
    $inserted = $this->slotService->generateRollingSlots(daysAhead: 1, courtId: $this->court->id);

    // 4 slots per day * 2 days = 8 slots
    expect($inserted)->toBe(8);

    // Re-running rolling generation creates 0 new slots (idempotent)
    $secondRun = $this->slotService->generateRollingSlots(daysAhead: 1, courtId: $this->court->id);
    expect($secondRun)->toBe(0);
});
