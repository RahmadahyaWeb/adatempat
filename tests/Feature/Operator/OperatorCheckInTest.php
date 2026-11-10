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

function createOperatorCheckInFixture(
    string $slotDate = 'today',
    PaymentStatus $paymentStatus = PaymentStatus::Paid,
    CheckInStatus $checkInStatus = CheckInStatus::Unclaimed,
    SubscriptionStatus $subscriptionStatus = SubscriptionStatus::Active,
): array {
    $date = $slotDate === 'today' ? Carbon::today()->toDateString() : $slotDate;

    $venue = Venue::create([
        'name' => 'Gelora Sports Hub',
        'slug' => 'gelora-sports-hub',
        'city' => 'Jakarta Selatan',
        'address' => 'Jl. TB Simatupang No. 15',
        'subscription_status' => $subscriptionStatus->value,
    ]);

    $operator = User::factory()->create([
        'name' => 'Operator Anton',
        'email' => 'anton@gelora.com',
        'role' => UserRole::VenueOperator,
        'venue_id' => $venue->id,
    ]);

    $court = Court::create([
        'venue_id' => $venue->id,
        'name' => 'Court A',
        'sport_type' => SportType::Futsal,
        'default_hourly_rate' => 150000,
        'is_active' => true,
    ]);

    $slot = CourtSlot::create([
        'court_id' => $court->id,
        'date' => $date,
        'start_time' => '19:00:00',
        'end_time' => '20:00:00',
        'price' => 150000,
        'status' => SlotStatus::Booked,
    ]);

    $player = User::factory()->create([
        'name' => 'Player Rahmat',
        'role' => UserRole::Player,
    ]);

    $booking = Booking::create([
        'booking_code' => 'ADT-20261110-GSH1',
        'user_id' => $player->id,
        'venue_id' => $venue->id,
        'total_amount' => 150000,
        'payment_status' => $paymentStatus,
        'check_in_status' => $checkInStatus,
        'qr_token' => Booking::generateQrToken(),
        'paid_at' => now(),
    ]);

    BookingItem::create([
        'booking_id' => $booking->id,
        'court_slot_id' => $slot->id,
        'price_locked' => 150000,
    ]);

    return compact('venue', 'operator', 'court', 'slot', 'player', 'booking');
}

test('operator can check in player via scanned QR token', function () {
    $env = createOperatorCheckInFixture();

    $response = $this->actingAs($env['operator'])
        ->postJson('/operator/check-in', [
            'qr_token' => $env['booking']->qr_token,
        ]);

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'message' => 'Player check-in verified successfully.',
            'data' => [
                'booking_code' => $env['booking']->booking_code,
                'check_in_status' => 'checked_in',
                'checked_in_by' => $env['operator']->id,
            ],
        ]);

    $freshBooking = $env['booking']->fresh();
    expect($freshBooking->check_in_status)->toBe(CheckInStatus::CheckedIn)
        ->and($freshBooking->checked_in_by)->toBe($env['operator']->id)
        ->and($freshBooking->checked_in_at)->not->toBeNull();
});

test('operator can check in player via manual booking code entry', function () {
    $env = createOperatorCheckInFixture();

    $response = $this->actingAs($env['operator'])
        ->postJson('/operator/check-in', [
            'booking_code' => $env['booking']->booking_code,
        ]);

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'data' => [
                'booking_code' => $env['booking']->booking_code,
                'check_in_status' => 'checked_in',
                'checked_in_by' => $env['operator']->id,
            ],
        ]);
});

test('operator from another venue cannot check in ticket (HTTP 403 Forbidden)', function () {
    $env = createOperatorCheckInFixture();
    $otherVenue = Venue::create([
        'name' => 'Other Venue Arena',
        'slug' => 'other-venue-arena',
        'city' => 'Surabaya',
        'address' => 'Jl. Pemuda No. 1',
        'subscription_status' => 'active',
    ]);
    $otherOperator = User::factory()->create([
        'name' => 'Operator Other',
        'role' => UserRole::VenueOperator,
        'venue_id' => $otherVenue->id,
    ]);

    $response = $this->actingAs($otherOperator)
        ->postJson('/operator/check-in', [
            'qr_token' => $env['booking']->qr_token,
        ]);

    $response->assertStatus(403);
});

test('check-in rejects unpaid booking with HTTP 422', function () {
    $env = createOperatorCheckInFixture(paymentStatus: PaymentStatus::Pending);

    $response = $this->actingAs($env['operator'])
        ->postJson('/operator/check-in', [
            'qr_token' => $env['booking']->qr_token,
        ]);

    $response->assertStatus(422)
        ->assertJsonFragment(['message' => 'Only paid bookings can be checked in.']);
});

test('check-in rejects booking scheduled for future date with HTTP 422', function () {
    $tomorrow = Carbon::tomorrow()->toDateString();
    $env = createOperatorCheckInFixture(slotDate: $tomorrow);

    $response = $this->actingAs($env['operator'])
        ->postJson('/operator/check-in', [
            'qr_token' => $env['booking']->qr_token,
        ]);

    $response->assertStatus(422)
        ->assertJsonFragment(['message' => 'Check-in is only permitted on the scheduled operational date.']);
});

test('duplicate check-in attempt returns HTTP 422 with original audit metadata', function () {
    $env = createOperatorCheckInFixture();
    $firstCheckedInAt = Carbon::now()->subMinutes(10);
    $env['booking']->update([
        'check_in_status' => CheckInStatus::CheckedIn,
        'checked_in_at' => $firstCheckedInAt,
        'checked_in_by' => $env['operator']->id,
    ]);

    $response = $this->actingAs($env['operator'])
        ->postJson('/operator/check-in', [
            'qr_token' => $env['booking']->qr_token,
        ]);

    $response->assertStatus(422)
        ->assertJsonStructure([
            'status',
            'message',
            'data' => [
                'checked_in_at',
                'checked_in_by',
                'staff_name',
            ],
        ])
        ->assertJsonFragment([
            'status' => 'error',
            'staff_name' => $env['operator']->name,
            'checked_in_by' => $env['operator']->id,
        ]);
});

test('operator of suspended venue cannot perform check-in mutations', function () {
    $env = createOperatorCheckInFixture(subscriptionStatus: SubscriptionStatus::Suspended);

    $response = $this->actingAs($env['operator'])
        ->postJson('/operator/check-in', [
            'qr_token' => $env['booking']->qr_token,
        ]);

    $response->assertStatus(403);
});
