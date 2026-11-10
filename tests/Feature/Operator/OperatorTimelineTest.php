<?php

declare(strict_types=1);

use App\Enums\CheckInStatus;
use App\Enums\PaymentStatus;
use App\Enums\SlotStatus;
use App\Enums\SportType;
use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Court;
use App\Models\CourtSlot;
use App\Models\User;
use App\Models\Venue;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow('2026-11-10 10:00:00');

    $this->venueA = Venue::create([
        'name' => 'Venue Alpha',
        'slug' => 'venue-alpha',
        'city' => 'Jakarta Selatan',
        'address' => 'Jl. Radio Dalam No. 1',
        'booking_horizon_days' => 14,
        'is_active' => true,
        'subscription_status' => SubscriptionStatus::Active,
    ]);

    $this->operatorA = User::create([
        'name' => 'Operator Alpha',
        'email' => 'alpha@venue.com',
        'role' => UserRole::VenueOperator,
        'venue_id' => $this->venueA->id,
    ]);

    $this->court1 = Court::create([
        'venue_id' => $this->venueA->id,
        'name' => 'Court 1',
        'sport_type' => SportType::Badminton,
        'opening_time' => '08:00:00',
        'closing_time' => '12:00:00',
        'default_hourly_rate' => 100000,
        'is_active' => true,
    ]);

    $this->court2 = Court::create([
        'venue_id' => $this->venueA->id,
        'name' => 'Court 2',
        'sport_type' => SportType::Padel,
        'opening_time' => '08:00:00',
        'closing_time' => '11:00:00',
        'default_hourly_rate' => 200000,
        'is_active' => true,
    ]);

    $this->venueB = Venue::create([
        'name' => 'Venue Beta',
        'slug' => 'venue-beta',
        'city' => 'Jakarta Barat',
        'address' => 'Jl. Panjang No. 2',
        'booking_horizon_days' => 7,
        'is_active' => true,
        'subscription_status' => SubscriptionStatus::Active,
    ]);

    $this->courtB = Court::create([
        'venue_id' => $this->venueB->id,
        'name' => 'Beta Court 1',
        'sport_type' => SportType::Tennis,
        'opening_time' => '08:00:00',
        'closing_time' => '12:00:00',
        'default_hourly_rate' => 150000,
        'is_active' => true,
    ]);

    $this->operatorB = User::create([
        'name' => 'Operator Beta',
        'email' => 'beta@venue.com',
        'role' => UserRole::VenueOperator,
        'venue_id' => $this->venueB->id,
    ]);

    $this->player = User::create([
        'name' => 'Player Rahmat',
        'email' => 'rahmat@example.com',
        'role' => UserRole::Player,
    ]);
});

afterEach(function () {
    Carbon::setTestNow();
});

test('operator can retrieve daily court schedule timeline with discrete 60-minute slots', function () {
    $response = $this->actingAs($this->operatorA)
        ->getJson(route('operator.timeline', ['date' => '2026-11-10']));

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.date', '2026-11-10')
        ->assertJsonPath('data.venue.id', $this->venueA->id)
        ->assertJsonPath('data.venue.name', 'Venue Alpha')
        ->assertJsonCount(2, 'data.courts')
        ->assertJsonPath('data.courts.0.name', 'Court 1')
        ->assertJsonCount(4, 'data.courts.0.slots')
        ->assertJsonPath('data.courts.0.slots.0.start_time', '08:00:00')
        ->assertJsonPath('data.courts.0.slots.0.end_time', '09:00:00')
        ->assertJsonPath('data.courts.0.slots.0.status', SlotStatus::Available->value);
});

test('timeline includes reservation details for booked slots and block reason for blocked slots', function () {
    // Prime slots via timeline call
    $this->actingAs($this->operatorA)->getJson(route('operator.timeline', ['date' => '2026-11-10']))->assertOk();

    $slotBooked = CourtSlot::where('court_id', $this->court1->id)
        ->where('date', '2026-11-10')
        ->where('start_time', '08:00:00')
        ->firstOrFail();

    $slotBooked->update(['status' => SlotStatus::Booked]);

    $booking = Booking::create([
        'booking_code' => 'ADT-20261110-PLAY',
        'user_id' => $this->player->id,
        'venue_id' => $this->venueA->id,
        'total_amount' => 100000,
        'payment_status' => PaymentStatus::Paid,
        'check_in_status' => CheckInStatus::CheckedIn,
        'qr_token' => Booking::generateQrToken(),
        'checked_in_at' => Carbon::parse('2026-11-10 08:02:00'),
        'checked_in_by' => $this->operatorA->id,
    ]);

    BookingItem::create([
        'booking_id' => $booking->id,
        'court_slot_id' => $slotBooked->id,
        'price_locked' => 100000,
    ]);

    $slotBlocked = CourtSlot::where('court_id', $this->court1->id)
        ->where('date', '2026-11-10')
        ->where('start_time', '09:00:00')
        ->firstOrFail();

    $slotBlocked->update([
        'status' => SlotStatus::Blocked,
        'block_reason' => 'Walk-in Cash - Team Garuda',
    ]);

    $response = $this->actingAs($this->operatorA)
        ->getJson(route('operator.timeline', ['date' => '2026-11-10']));

    $response->assertOk();

    $court1Data = collect($response->json('data.courts'))->firstWhere('id', $this->court1->id);
    $slot0 = collect($court1Data['slots'])->firstWhere('id', $slotBooked->id);
    $slot1 = collect($court1Data['slots'])->firstWhere('id', $slotBlocked->id);

    expect($slot0['status'])->toBe(SlotStatus::Booked->value)
        ->and($slot0['booking']['booking_code'])->toBe('ADT-20261110-PLAY')
        ->and($slot0['booking']['customer_id'])->toBe($this->player->id)
        ->and($slot0['booking']['customer_name'])->toBe('Player Rahmat')
        ->and($slot0['booking']['payment_status'])->toBe(PaymentStatus::Paid->value)
        ->and($slot0['booking']['check_in_status'])->toBe(CheckInStatus::CheckedIn->value)
        ->and($slot0['booking']['checked_in_at'])->toBe(Carbon::parse('2026-11-10 08:02:00')->toISOString())
        ->and($slot1['status'])->toBe(SlotStatus::Blocked->value)
        ->and($slot1['block_reason'])->toBe('Walk-in Cash - Team Garuda')
        ->and($slot1['booking'])->toBeNull();
});

test('operator can filter timeline by specific court_id within their venue', function () {
    $response = $this->actingAs($this->operatorA)
        ->getJson(route('operator.timeline', [
            'date' => '2026-11-10',
            'court_id' => $this->court2->id,
        ]));

    $response->assertOk()
        ->assertJsonCount(1, 'data.courts')
        ->assertJsonPath('data.courts.0.id', $this->court2->id);
});

test('operator cannot filter timeline with court belonging to another venue (HTTP 403 Forbidden)', function () {
    $response = $this->actingAs($this->operatorA)
        ->getJson(route('operator.timeline', [
            'date' => '2026-11-10',
            'court_id' => $this->courtB->id,
        ]));

    $response->assertForbidden();
});

test('querying timeline for past dates or beyond horizon is rejected (HTTP 403 Forbidden)', function () {
    $pastResponse = $this->actingAs($this->operatorA)
        ->getJson(route('operator.timeline', ['date' => '2026-11-09']));

    $pastResponse->assertForbidden();

    $futureResponse = $this->actingAs($this->operatorA)
        ->getJson(route('operator.timeline', ['date' => '2026-11-30']));

    $futureResponse->assertForbidden();
});

test('unauthenticated user cannot access timeline (HTTP 401)', function () {
    $response = $this->getJson(route('operator.timeline', ['date' => '2026-11-10']));
    $response->assertUnauthorized();
});

test('player role cannot access operator timeline (HTTP 403 Forbidden)', function () {
    $response = $this->actingAs($this->player)
        ->getJson(route('operator.timeline', ['date' => '2026-11-10']));
    $response->assertForbidden();
});
