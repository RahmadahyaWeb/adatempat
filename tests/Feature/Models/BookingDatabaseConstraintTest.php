<?php

declare(strict_types=1);

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
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('booking and booking_items persist with composite unique constraint allowing re-booking across bookings', function () {
    $venue = Venue::create([
        'name' => 'Senayan Arena',
        'slug' => 'senayan-arena',
        'city' => 'Jakarta Pusat',
        'address' => 'Jl. Asia Afrika',
        'booking_horizon_days' => 30,
        'allow_reschedule' => true,
        'is_active' => true,
        'subscription_status' => SubscriptionStatus::Active,
    ]);

    $court = Court::create([
        'venue_id' => $venue->id,
        'name' => 'Court 1',
        'sport_type' => SportType::Badminton,
        'opening_time' => '07:00:00',
        'closing_time' => '23:00:00',
        'default_hourly_rate' => 100000,
        'is_active' => true,
    ]);

    $slot = CourtSlot::create([
        'court_id' => $court->id,
        'date' => '2026-11-20',
        'start_time' => '10:00:00',
        'end_time' => '11:00:00',
        'price' => 100000,
        'status' => SlotStatus::Available,
    ]);

    $player1 = User::create([
        'name' => 'Player One',
        'email' => 'player1@example.com',
        'role' => UserRole::Player,
    ]);

    $player2 = User::create([
        'name' => 'Player Two',
        'email' => 'player2@example.com',
        'role' => UserRole::Player,
    ]);

    // Booking 1 for slot
    $booking1 = Booking::create([
        'booking_code' => Booking::generateBookingCode(),
        'user_id' => $player1->id,
        'venue_id' => $venue->id,
        'total_amount' => 100000,
        'payment_status' => PaymentStatus::Expired,
        'qr_token' => Booking::generateQrToken(),
    ]);

    $item1 = BookingItem::create([
        'booking_id' => $booking1->id,
        'court_slot_id' => $slot->id,
        'price_locked' => 100000,
    ]);

    expect($item1->id)->not->toBeNull();

    // Attempting duplicate (booking1, slot) must fail unique constraint
    expect(function () use ($booking1, $slot) {
        BookingItem::create([
            'booking_id' => $booking1->id,
            'court_slot_id' => $slot->id,
            'price_locked' => 100000,
        ]);
    })->toThrow(QueryException::class);

    // Booking 2 (different booking) for the SAME slot must SUCCEED due to composite unique (booking_id, court_slot_id)
    $booking2 = Booking::create([
        'booking_code' => Booking::generateBookingCode(),
        'user_id' => $player2->id,
        'venue_id' => $venue->id,
        'total_amount' => 100000,
        'payment_status' => PaymentStatus::Pending,
        'qr_token' => Booking::generateQrToken(),
    ]);

    $item2 = BookingItem::create([
        'booking_id' => $booking2->id,
        'court_slot_id' => $slot->id,
        'price_locked' => 100000,
    ]);

    expect($item2->id)->not->toBeNull()
        ->and($item2->court_slot_id)->toBe($slot->id)
        ->and(BookingItem::where('court_slot_id', $slot->id)->count())->toBe(2);
});
