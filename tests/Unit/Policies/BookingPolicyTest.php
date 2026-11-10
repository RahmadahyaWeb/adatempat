<?php

declare(strict_types=1);

use App\Enums\CheckInStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\User;
use App\Models\Venue;
use App\Policies\BookingPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createTestVenue(string $slug = 'test-venue'): Venue
{
    return Venue::create([
        'name' => 'Test Venue',
        'slug' => $slug,
        'city' => 'Jakarta Selatan',
        'address' => 'Jl. Radio Dalam No. 1',
        'subscription_status' => 'active',
    ]);
}

function createTestBooking(int $userId, int $venueId): Booking
{
    return Booking::create([
        'booking_code' => Booking::generateBookingCode(),
        'user_id' => $userId,
        'venue_id' => $venueId,
        'total_amount' => 100000,
        'payment_status' => PaymentStatus::Paid,
        'check_in_status' => CheckInStatus::Unclaimed,
        'qr_token' => Booking::generateQrToken(),
    ]);
}

test('player can view their own booking', function () {
    $player = User::factory()->create(['role' => UserRole::Player]);
    $venue = createTestVenue('venue-1');
    $booking = createTestBooking($player->id, $venue->id);

    $policy = new BookingPolicy;

    expect($policy->view($player, $booking))->toBeTrue();
});

test('player cannot view booking belonging to another player', function () {
    $player = User::factory()->create(['role' => UserRole::Player]);
    $otherPlayer = User::factory()->create(['role' => UserRole::Player]);
    $venue = createTestVenue('venue-2');
    $booking = createTestBooking($otherPlayer->id, $venue->id);

    $policy = new BookingPolicy;

    expect($policy->view($player, $booking))->toBeFalse();
});

test('operator can view and check in booking for their assigned venue', function () {
    $venue = createTestVenue('venue-3');
    $operator = User::factory()->create([
        'role' => UserRole::VenueOperator,
        'venue_id' => $venue->id,
    ]);
    $booking = createTestBooking(User::factory()->create()->id, $venue->id);

    $policy = new BookingPolicy;

    expect($policy->view($operator, $booking))->toBeTrue()
        ->and($policy->checkIn($operator, $booking))->toBeTrue();
});

test('operator cannot view or check in booking belonging to another venue', function () {
    $venueA = createTestVenue('venue-a');
    $venueB = createTestVenue('venue-b');
    $operatorA = User::factory()->create([
        'role' => UserRole::VenueOperator,
        'venue_id' => $venueA->id,
    ]);
    $bookingB = createTestBooking(User::factory()->create()->id, $venueB->id);

    $policy = new BookingPolicy;

    expect($policy->view($operatorA, $bookingB))->toBeFalse()
        ->and($policy->checkIn($operatorA, $bookingB))->toBeFalse();
});
