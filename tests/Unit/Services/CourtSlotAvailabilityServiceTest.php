<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\DayType;
use App\Enums\SlotStatus;
use App\Enums\SportType;
use App\Enums\SubscriptionStatus;
use App\Exceptions\BookingHorizonExceededException;
use App\Models\Court;
use App\Models\CourtPricingRule;
use App\Models\CourtSlot;
use App\Models\Venue;
use App\Services\CourtSlotAvailabilityService;
use App\Services\PricingResolutionService;
use App\Services\SlotGenerationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow('2026-11-10 10:00:00'); // Tuesday

    $this->venue = Venue::create([
        'name' => 'Senayan Badminton Center',
        'slug' => 'senayan-badminton-center',
        'city' => 'Jakarta Pusat',
        'address' => 'Jl. Asia Afrika',
        'booking_horizon_days' => 14,
        'allow_reschedule' => true,
        'is_active' => true,
        'subscription_status' => SubscriptionStatus::Active,
        'subscription_expires_at' => Carbon::parse('2026-12-31 23:59:59'),
    ]);

    $this->court1 = Court::create([
        'venue_id' => $this->venue->id,
        'name' => 'Court 1 - Premium',
        'sport_type' => SportType::Badminton,
        'opening_time' => '08:00:00',
        'closing_time' => '12:00:00', // 4 discrete slots: 08-09, 09-10, 10-11, 11-12
        'default_hourly_rate' => 100000,
        'is_active' => true,
    ]);

    $this->court2 = Court::create([
        'venue_id' => $this->venue->id,
        'name' => 'Court 2 - Standard',
        'sport_type' => SportType::Badminton,
        'opening_time' => '08:00:00',
        'closing_time' => '10:00:00', // 2 discrete slots: 08-09, 09-10
        'default_hourly_rate' => 120000,
        'is_active' => true,
    ]);

    CourtPricingRule::create([
        'court_id' => $this->court1->id,
        'day_type' => DayType::Weekday,
        'start_time' => '08:00:00',
        'end_time' => '10:00:00',
        'price_per_hour' => 150000,
        'tier_name' => 'Morning Peak',
    ]);

    $pricingService = new PricingResolutionService;
    $slotGenService = new SlotGenerationService($pricingService);
    $this->service = new CourtSlotAvailabilityService($slotGenService);
});

afterEach(function () {
    Carbon::setTestNow();
});

test('getAvailableSlots generates slots just-in-time and returns formatted array matching public API schema', function () {
    $date = '2026-11-12'; // Thursday, within 14-day horizon

    expect(CourtSlot::where('court_id', $this->court1->id)->where('date', $date)->count())->toBe(0);

    $slots = $this->service->getAvailableSlots($this->court1, $date);

    expect($slots)->toBeArray()->toHaveCount(4);
    expect($slots[0])->toMatchArray([
        'court_id' => $this->court1->id,
        'date' => $date,
        'start_time' => '08:00:00',
        'end_time' => '09:00:00',
        'price' => 150000,
        'status' => 'available',
        'is_bookable' => true,
    ]);
    expect($slots[2])->toMatchArray([
        'court_id' => $this->court1->id,
        'date' => $date,
        'start_time' => '10:00:00',
        'end_time' => '11:00:00',
        'price' => 100000,
        'status' => 'available',
        'is_bookable' => true,
    ]);

    // Slots are now persisted in database
    expect(CourtSlot::where('court_id', $this->court1->id)->where('date', $date)->count())->toBe(4);
});

test('getAvailableSlots dynamically evaluates active and expired checkout holds', function () {
    $date = '2026-11-12';
    $this->service->getAvailableSlots($this->court1, $date);

    $firstSlot = CourtSlot::where('court_id', $this->court1->id)
        ->where('date', $date)
        ->where('start_time', '08:00:00')
        ->firstOrFail();

    $secondSlot = CourtSlot::where('court_id', $this->court1->id)
        ->where('date', $date)
        ->where('start_time', '09:00:00')
        ->firstOrFail();

    // Active hold
    $firstSlot->update([
        'status' => SlotStatus::Held,
        'locked_until' => now()->addMinutes(10),
    ]);

    // Expired hold
    $secondSlot->update([
        'status' => SlotStatus::Held,
        'locked_until' => now()->subMinutes(5),
    ]);

    $slots = $this->service->getAvailableSlots($this->court1, $date);
    $activeHold = collect($slots)->firstWhere('start_time', '08:00:00');
    $expiredHold = collect($slots)->firstWhere('start_time', '09:00:00');

    expect($activeHold['status'])->toBe('held')
        ->and($activeHold['is_bookable'])->toBeFalse()
        ->and($expiredHold['status'])->toBe('available')
        ->and($expiredHold['is_bookable'])->toBeTrue();
});

test('getAvailableSlots throws BookingHorizonExceededException when date is beyond horizon or in the past', function () {
    // Past date
    expect(fn () => $this->service->getAvailableSlots($this->court1, '2026-11-09'))
        ->toThrow(BookingHorizonExceededException::class);

    // Beyond horizon (horizon is 14 days)
    expect(fn () => $this->service->getAvailableSlots($this->court1, '2026-11-26'))
        ->toThrow(BookingHorizonExceededException::class);
});

test('getSlotsForCourt generates and returns Eloquent Collection for operator', function () {
    $date = '2026-11-12';
    $slots = $this->service->getSlotsForCourt($this->court1, $date);

    expect($slots)->toHaveCount(4);
    expect($slots->first())->toBeInstanceOf(CourtSlot::class);
    expect($slots->first()->start_time)->toBe('08:00:00');
});

test('getTimelineSlotsForCourts generates slots across all courts and returns Collection grouped by court_id', function () {
    $date = '2026-11-12';
    $courts = collect([$this->court1, $this->court2]);

    $groupedSlots = $this->service->getTimelineSlotsForCourts($this->venue, $courts, $date);

    expect($groupedSlots)->toHaveKeys([$this->court1->id, $this->court2->id]);
    expect($groupedSlots->get($this->court1->id))->toHaveCount(4);
    expect($groupedSlots->get($this->court2->id))->toHaveCount(2);

    // Verify slots are persisted in DB
    expect(CourtSlot::where('court_id', $this->court1->id)->where('date', $date)->count())->toBe(4);
    expect(CourtSlot::where('court_id', $this->court2->id)->where('date', $date)->count())->toBe(2);
});
