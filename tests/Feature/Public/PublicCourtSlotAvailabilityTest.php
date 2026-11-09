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
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow('2026-11-10 09:00:00'); // Tuesday morning

    $this->venue = Venue::create([
        'name' => 'Senayan Badminton Center',
        'slug' => 'senayan-badminton-center',
        'city' => 'Jakarta Pusat',
        'address' => 'Jl. Pintu Satu Senayan',
        'booking_horizon_days' => 14,
        'allow_reschedule' => true,
        'is_active' => true,
        'subscription_status' => SubscriptionStatus::Active,
        'subscription_expires_at' => Carbon::parse('2026-12-31 23:59:59'),
    ]);

    $this->court = Court::create([
        'venue_id' => $this->venue->id,
        'name' => 'Court 1 - Premium BWF',
        'sport_type' => SportType::Badminton,
        'opening_time' => '08:00:00',
        'closing_time' => '12:00:00', // 4 one-hour discrete slots: 08-09, 09-10, 10-11, 11-12
        'default_hourly_rate' => 100000,
        'is_active' => true,
    ]);

    // Weekday peak pricing rule for 08:00 - 10:00
    CourtPricingRule::create([
        'court_id' => $this->court->id,
        'day_type' => DayType::Weekday,
        'start_time' => '08:00:00',
        'end_time' => '10:00:00',
        'price_per_hour' => 150000,
        'tier_name' => 'Morning Peak',
    ]);
});

afterEach(function () {
    Carbon::setTestNow();
});

test('public court slot endpoint triggers JIT generation transparently and returns chronological 60-minute slots', function () {
    // 2026-11-12 is Thursday (2 days ahead, within 14-day horizon)
    expect(CourtSlot::where('court_id', $this->court->id)->where('date', '2026-11-12')->count())->toBe(0);

    $response = $this->getJson("/api/courts/{$this->court->id}/slots?date=2026-11-12");

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonCount(4, 'data')
        ->assertJsonPath('data.0.start_time', '08:00:00')
        ->assertJsonPath('data.0.end_time', '09:00:00')
        ->assertJsonPath('data.0.price', 150000)
        ->assertJsonPath('data.0.status', 'available')
        ->assertJsonPath('data.0.is_bookable', true)
        ->assertJsonPath('data.1.start_time', '09:00:00')
        ->assertJsonPath('data.1.price', 150000)
        ->assertJsonPath('data.2.start_time', '10:00:00')
        ->assertJsonPath('data.2.price', 100000) // Default fallback
        ->assertJsonPath('data.3.start_time', '11:00:00')
        ->assertJsonPath('data.3.price', 100000);

    // Slots are persisted in DB
    expect(CourtSlot::where('court_id', $this->court->id)->where('date', '2026-11-12')->count())->toBe(4);
});

test('slots currently in checkout hold with unelapsed duration are marked as unavailable for selection', function () {
    // Pre-generate slots for 2026-11-12
    $this->getJson("/api/courts/{$this->court->id}/slots?date=2026-11-12")->assertOk();

    // Slot 08:00-09:00 is placed on checkout hold with 10-minute future locked_until
    $heldSlot = CourtSlot::where('court_id', $this->court->id)
        ->where('date', '2026-11-12')
        ->where('start_time', '08:00:00')
        ->firstOrFail();

    $heldSlot->update([
        'status' => SlotStatus::Held,
        'locked_until' => now()->addMinutes(10),
    ]);

    $response = $this->getJson("/api/courts/{$this->court->id}/slots?date=2026-11-12");

    $response->assertOk();
    $slotData = collect($response->json('data'))->firstWhere('start_time', '08:00:00');

    expect($slotData['status'])->toBe('held')
        ->and($slotData['is_bookable'])->toBeFalse();
});

test('slots with expired checkout holds are dynamically evaluated as available', function () {
    // Pre-generate slots for 2026-11-12
    $this->getJson("/api/courts/{$this->court->id}/slots?date=2026-11-12")->assertOk();

    // Slot 09:00-10:00 had a hold that expired 2 minutes ago
    $expiredHoldSlot = CourtSlot::where('court_id', $this->court->id)
        ->where('date', '2026-11-12')
        ->where('start_time', '09:00:00')
        ->firstOrFail();

    $expiredHoldSlot->update([
        'status' => SlotStatus::Held,
        'locked_until' => now()->subMinutes(2),
    ]);

    $response = $this->getJson("/api/courts/{$this->court->id}/slots?date=2026-11-12");

    $response->assertOk();
    $slotData = collect($response->json('data'))->firstWhere('start_time', '09:00:00');

    expect($slotData['status'])->toBe('available')
        ->and($slotData['is_bookable'])->toBeTrue();
});

test('booked and blocked slots are marked as non-bookable', function () {
    // Pre-generate slots for 2026-11-12
    $this->getJson("/api/courts/{$this->court->id}/slots?date=2026-11-12")->assertOk();

    CourtSlot::where('court_id', $this->court->id)
        ->where('date', '2026-11-12')
        ->where('start_time', '10:00:00')
        ->update(['status' => SlotStatus::Booked]);

    CourtSlot::where('court_id', $this->court->id)
        ->where('date', '2026-11-12')
        ->where('start_time', '11:00:00')
        ->update(['status' => SlotStatus::Blocked, 'block_reason' => 'Routine Maintenance']);

    $response = $this->getJson("/api/courts/{$this->court->id}/slots?date=2026-11-12");

    $response->assertOk();
    $slot10 = collect($response->json('data'))->firstWhere('start_time', '10:00:00');
    $slot11 = collect($response->json('data'))->firstWhere('start_time', '11:00:00');

    expect($slot10['status'])->toBe('booked')
        ->and($slot10['is_bookable'])->toBeFalse()
        ->and($slot11['status'])->toBe('blocked')
        ->and($slot11['is_bookable'])->toBeFalse();
});

test('date query exceeding venue booking horizon returns clear validation error', function () {
    // Horizon is 14 days, querying 20 days ahead
    $exceededDate = now()->addDays(20)->toDateString();

    $response = $this->getJson("/api/courts/{$this->court->id}/slots?date={$exceededDate}");

    $response->assertStatus(422)
        ->assertJsonPath('status', 'error')
        ->assertJsonPath('message', "Date {$exceededDate} exceeds venue booking horizon of 14 days.");
});

test('date query for past date returns clear validation error', function () {
    $pastDate = now()->subDay()->toDateString();

    $response = $this->getJson("/api/courts/{$this->court->id}/slots?date={$pastDate}");

    $response->assertStatus(422)
        ->assertJsonPath('status', 'error')
        ->assertJsonPath('message', 'Court slots cannot be queried or generated for past dates.');
});

test('querying slots for court belonging to suspended or expired venue returns 404', function () {
    // Suspend the venue
    $this->venue->update(['subscription_status' => SubscriptionStatus::Suspended]);

    $response = $this->getJson("/api/courts/{$this->court->id}/slots?date=2026-11-12");

    $response->assertNotFound()
        ->assertJsonPath('status', 'error');
});
