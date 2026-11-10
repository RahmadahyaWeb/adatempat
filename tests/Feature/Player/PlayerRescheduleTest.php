<?php

declare(strict_types=1);

use App\Enums\CheckInStatus;
use App\Enums\PaymentStatus;
use App\Enums\SlotStatus;
use App\Enums\SportType;
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

function createPlayerRescheduleFixture(
    bool $allowReschedule = true,
    int $slotDaysInFuture = 4,
    string $slotStartTime = '10:00:00',
    PaymentStatus $paymentStatus = PaymentStatus::Paid,
    CheckInStatus $checkInStatus = CheckInStatus::Unclaimed,
    int $price = 120000,
): array {
    $slotDate = Carbon::now()->addDays($slotDaysInFuture)->toDateString();
    $replacementDate = Carbon::now()->addDays($slotDaysInFuture + 1)->toDateString();

    $venue = Venue::create([
        'name' => 'Mega Arena GBK',
        'slug' => 'mega-arena-gbk',
        'city' => 'Jakarta Pusat',
        'address' => 'Jl. Pintu Satu Senayan',
        'subscription_status' => 'active',
        'allow_reschedule' => $allowReschedule,
    ]);

    $court = Court::create([
        'venue_id' => $venue->id,
        'name' => 'Badminton Court 1',
        'sport_type' => SportType::Badminton,
        'default_hourly_rate' => $price,
        'is_active' => true,
    ]);

    $originalSlot = CourtSlot::create([
        'court_id' => $court->id,
        'date' => $slotDate,
        'start_time' => $slotStartTime,
        'end_time' => Carbon::parse($slotStartTime)->addHour()->format('H:i:s'),
        'price' => $price,
        'status' => SlotStatus::Booked,
    ]);

    $replacementSlot = CourtSlot::create([
        'court_id' => $court->id,
        'date' => $replacementDate,
        'start_time' => '15:00:00',
        'end_time' => '16:00:00',
        'price' => $price,
        'status' => SlotStatus::Available,
    ]);

    $player = User::factory()->create([
        'name' => 'Player Rahmat',
        'email' => 'rahmat@example.com',
        'role' => UserRole::Player,
    ]);

    $booking = Booking::create([
        'booking_code' => Booking::generateBookingCode(),
        'user_id' => $player->id,
        'venue_id' => $venue->id,
        'total_amount' => $price,
        'payment_status' => $paymentStatus,
        'check_in_status' => $checkInStatus,
        'qr_token' => Booking::generateQrToken(),
        'paid_at' => now(),
    ]);

    $bookingItem = BookingItem::create([
        'booking_id' => $booking->id,
        'court_slot_id' => $originalSlot->id,
        'price_locked' => $price,
    ]);

    return compact('venue', 'court', 'originalSlot', 'replacementSlot', 'player', 'booking', 'bookingItem');
}

test('unauthenticated user cannot reschedule booking (HTTP 401)', function () {
    $fixture = createPlayerRescheduleFixture();

    $response = $this->postJson("/player/bookings/{$fixture['booking']->booking_code}/reschedule", [
        'replacement_slot_id' => $fixture['replacementSlot']->id,
    ]);

    $response->assertStatus(401);
});

test('player cannot reschedule booking belonging to another player (HTTP 403 Forbidden)', function () {
    $fixture = createPlayerRescheduleFixture();
    $otherPlayer = User::factory()->create(['role' => UserRole::Player]);

    $response = $this->actingAs($otherPlayer)
        ->postJson("/player/bookings/{$fixture['booking']->booking_code}/reschedule", [
            'replacement_slot_id' => $fixture['replacementSlot']->id,
        ]);

    $response->assertStatus(403);
});

test('player can successfully reschedule booking with atomic slot swap and updated ticket (HTTP 200)', function () {
    $fixture = createPlayerRescheduleFixture();

    $response = $this->actingAs($fixture['player'])
        ->postJson("/player/bookings/{$fixture['booking']->booking_code}/reschedule", [
            'replacement_slot_id' => $fixture['replacementSlot']->id,
        ]);

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'message' => 'Booking rescheduled successfully.',
            'data' => [
                'booking_code' => $fixture['booking']->booking_code,
                'payment_status' => 'rescheduled',
                'check_in_status' => 'unclaimed',
                'total_amount' => 120000,
                'venue' => [
                    'id' => $fixture['venue']->id,
                    'name' => 'Mega Arena GBK',
                ],
            ],
        ])
        ->assertJsonStructure([
            'data' => [
                'rescheduled_at',
                'qr_token',
                'items' => [
                    '*' => [
                        'court_id',
                        'court_name',
                        'sport_type',
                        'date',
                        'start_time',
                        'end_time',
                        'price_locked',
                    ],
                ],
            ],
        ]);

    // Verify DB states
    expect($fixture['originalSlot']->fresh()->status)->toBe(SlotStatus::Available)
        ->and($fixture['replacementSlot']->fresh()->status)->toBe(SlotStatus::Booked);

    $freshBooking = $fixture['booking']->fresh();
    expect($freshBooking->payment_status)->toBe(PaymentStatus::Rescheduled)
        ->and($freshBooking->rescheduled_at)->not->toBeNull()
        ->and($freshBooking->items->first()->court_slot_id)->toBe($fixture['replacementSlot']->id);
});

test('reschedule endpoint works via profile alias /profile/bookings/{booking_code}/reschedule', function () {
    $fixture = createPlayerRescheduleFixture();

    $response = $this->actingAs($fixture['player'])
        ->postJson("/profile/bookings/{$fixture['booking']->booking_code}/reschedule", [
            'replacement_slot_id' => $fixture['replacementSlot']->id,
        ]);

    $response->assertStatus(200)
        ->assertJsonFragment([
            'payment_status' => 'rescheduled',
        ]);
});

test('reschedule endpoint rejects when venue disables rescheduling with HTTP 422', function () {
    $fixture = createPlayerRescheduleFixture(allowReschedule: false);

    $response = $this->actingAs($fixture['player'])
        ->postJson("/player/bookings/{$fixture['booking']->booking_code}/reschedule", [
            'replacement_slot_id' => $fixture['replacementSlot']->id,
        ]);

    $response->assertStatus(422)
        ->assertJsonFragment([
            'status' => 'error',
            'message' => 'Venue policy does not permit rescheduling.',
        ]);
});

test('reschedule endpoint rejects late request within 24h cutoff with HTTP 422', function () {
    // Current time is 10:00:00, slot starts in 12 hours (22:00:00)
    Carbon::setTestNow(Carbon::parse('2026-11-10 10:00:00'));

    $fixture = createPlayerRescheduleFixture(slotDaysInFuture: 0, slotStartTime: '22:00:00');

    $response = $this->actingAs($fixture['player'])
        ->postJson("/player/bookings/{$fixture['booking']->booking_code}/reschedule", [
            'replacement_slot_id' => $fixture['replacementSlot']->id,
        ]);

    $response->assertStatus(422)
        ->assertJsonFragment([
            'status' => 'error',
            'message' => 'Reschedule must be initiated at least 24 hours prior to the booked slot start time.',
        ]);

    Carbon::setTestNow();
});

test('reschedule endpoint rejects subsequent reschedule attempt enforcing single-use limit with HTTP 422', function () {
    $fixture = createPlayerRescheduleFixture();

    // First reschedule
    $this->actingAs($fixture['player'])
        ->postJson("/player/bookings/{$fixture['booking']->booking_code}/reschedule", [
            'replacement_slot_id' => $fixture['replacementSlot']->id,
        ])
        ->assertStatus(200);

    // Second replacement slot
    $thirdSlot = CourtSlot::create([
        'court_id' => $fixture['court']->id,
        'date' => Carbon::now()->addDays(6)->toDateString(),
        'start_time' => '16:00:00',
        'end_time' => '17:00:00',
        'price' => 120000,
        'status' => SlotStatus::Available,
    ]);

    // Second reschedule attempt
    $response = $this->actingAs($fixture['player'])
        ->postJson("/player/bookings/{$fixture['booking']->booking_code}/reschedule", [
            'replacement_slot_id' => $thirdSlot->id,
        ]);

    $response->assertStatus(422)
        ->assertJsonFragment([
            'status' => 'error',
            'message' => 'This booking has already been rescheduled.',
        ]);
});

test('reschedule endpoint rejects replacement slot with price disparity with HTTP 422', function () {
    $fixture = createPlayerRescheduleFixture();
    $expensiveSlot = CourtSlot::create([
        'court_id' => $fixture['court']->id,
        'date' => Carbon::now()->addDays(5)->toDateString(),
        'start_time' => '19:00:00',
        'end_time' => '20:00:00',
        'price' => 200000, // Disparity
        'status' => SlotStatus::Available,
    ]);

    $response = $this->actingAs($fixture['player'])
        ->postJson("/player/bookings/{$fixture['booking']->booking_code}/reschedule", [
            'replacement_slot_id' => $expensiveSlot->id,
        ]);

    $response->assertStatus(422)
        ->assertJsonFragment([
            'status' => 'error',
            'message' => 'Replacement slot price must match the original booked price exactly.',
        ]);
});

test('reschedule endpoint rejects unavailable replacement slot with HTTP 409', function () {
    $fixture = createPlayerRescheduleFixture();
    $fixture['replacementSlot']->update(['status' => SlotStatus::Booked]);

    $response = $this->actingAs($fixture['player'])
        ->postJson("/player/bookings/{$fixture['booking']->booking_code}/reschedule", [
            'replacement_slot_id' => $fixture['replacementSlot']->id,
        ]);

    $response->assertStatus(409);
});

test('reschedule endpoint rejects replacement slot scheduled in the past with HTTP 422', function () {
    $fixture = createPlayerRescheduleFixture();
    $pastSlot = CourtSlot::create([
        'court_id' => $fixture['court']->id,
        'date' => Carbon::yesterday()->toDateString(),
        'start_time' => '10:00:00',
        'end_time' => '11:00:00',
        'price' => 120000,
        'status' => SlotStatus::Available,
    ]);

    $response = $this->actingAs($fixture['player'])
        ->postJson("/player/bookings/{$fixture['booking']->booking_code}/reschedule", [
            'replacement_slot_id' => $pastSlot->id,
        ]);

    $response->assertStatus(422)
        ->assertJsonFragment([
            'status' => 'error',
            'message' => 'Replacement slot must be scheduled in the future.',
        ]);
});

test('digital ticket displays updated court slot times and preserves rescheduled_at audit timestamp', function () {
    $fixture = createPlayerRescheduleFixture();

    $this->actingAs($fixture['player'])
        ->postJson("/player/bookings/{$fixture['booking']->booking_code}/reschedule", [
            'replacement_slot_id' => $fixture['replacementSlot']->id,
        ])
        ->assertStatus(200);

    // Retrieve digital ticket via GET /player/tickets/{code}
    $response = $this->actingAs($fixture['player'])
        ->getJson("/player/tickets/{$fixture['booking']->booking_code}");

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'data' => [
                'booking_code' => $fixture['booking']->booking_code,
                'payment_status' => 'rescheduled',
                'items' => [
                    [
                        'court_id' => $fixture['court']->id,
                        'date' => $fixture['replacementSlot']->date->toDateString(),
                        'start_time' => '15:00:00',
                        'end_time' => '16:00:00',
                    ],
                ],
            ],
        ])
        ->assertJsonStructure([
            'data' => [
                'rescheduled_at',
                'qr_token',
            ],
        ]);

    expect($response->json('data.rescheduled_at'))->not->toBeNull()
        ->and($response->json('data.qr_token'))->not->toBeNull();
});
