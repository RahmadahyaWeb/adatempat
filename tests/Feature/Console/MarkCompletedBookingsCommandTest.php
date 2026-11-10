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

test('command transitions elapsed paid bookings to completed status', function () {
    $venue = Venue::create([
        'name' => 'Senayan Gelora',
        'slug' => 'senayan-gelora',
        'city' => 'Jakarta Pusat',
        'address' => 'Jl. Gelora Bung Karno',
        'subscription_status' => 'active',
    ]);

    $court = Court::create([
        'venue_id' => $venue->id,
        'name' => 'Court 1',
        'sport_type' => SportType::Badminton,
        'default_hourly_rate' => 100000,
        'is_active' => true,
    ]);

    $player = User::factory()->create(['role' => UserRole::Player]);

    // 1. Elapsed booking: slot ended 2 hours ago
    $pastSlot = CourtSlot::create([
        'court_id' => $court->id,
        'date' => Carbon::today()->toDateString(),
        'start_time' => Carbon::now()->subHours(3)->format('H:i:s'),
        'end_time' => Carbon::now()->subHours(2)->format('H:i:s'),
        'price' => 100000,
        'status' => SlotStatus::Booked,
    ]);

    $elapsedBooking = Booking::create([
        'booking_code' => 'ADT-ELAPSED-1',
        'user_id' => $player->id,
        'venue_id' => $venue->id,
        'total_amount' => 100000,
        'payment_status' => PaymentStatus::Paid,
        'check_in_status' => CheckInStatus::CheckedIn,
        'qr_token' => Booking::generateQrToken(),
        'paid_at' => Carbon::now()->subHours(4),
    ]);

    BookingItem::create([
        'booking_id' => $elapsedBooking->id,
        'court_slot_id' => $pastSlot->id,
        'price_locked' => 100000,
    ]);

    // 2. Future booking: slot ends 2 hours from now (should remain paid)
    $futureSlot = CourtSlot::create([
        'court_id' => $court->id,
        'date' => Carbon::today()->toDateString(),
        'start_time' => Carbon::now()->addHour()->format('H:i:s'),
        'end_time' => Carbon::now()->addHours(2)->format('H:i:s'),
        'price' => 100000,
        'status' => SlotStatus::Booked,
    ]);

    $futureBooking = Booking::create([
        'booking_code' => 'ADT-FUTURE-1',
        'user_id' => $player->id,
        'venue_id' => $venue->id,
        'total_amount' => 100000,
        'payment_status' => PaymentStatus::Paid,
        'check_in_status' => CheckInStatus::Unclaimed,
        'qr_token' => Booking::generateQrToken(),
        'paid_at' => Carbon::now(),
    ]);

    BookingItem::create([
        'booking_id' => $futureBooking->id,
        'court_slot_id' => $futureSlot->id,
        'price_locked' => 100000,
    ]);

    // 3. Multi-slot booking where one slot ended but second slot is still active
    $activeBooking = Booking::create([
        'booking_code' => 'ADT-MULTI-ACTIVE',
        'user_id' => $player->id,
        'venue_id' => $venue->id,
        'total_amount' => 200000,
        'payment_status' => PaymentStatus::Paid,
        'check_in_status' => CheckInStatus::CheckedIn,
        'qr_token' => Booking::generateQrToken(),
        'paid_at' => Carbon::now()->subHours(2),
    ]);

    BookingItem::create([
        'booking_id' => $activeBooking->id,
        'court_slot_id' => $pastSlot->id,
        'price_locked' => 100000,
    ]);
    BookingItem::create([
        'booking_id' => $activeBooking->id,
        'court_slot_id' => $futureSlot->id,
        'price_locked' => 100000,
    ]);

    // 4. Pending booking in the past (should NOT be marked completed)
    $pendingBooking = Booking::create([
        'booking_code' => 'ADT-PENDING-PAST',
        'user_id' => $player->id,
        'venue_id' => $venue->id,
        'total_amount' => 100000,
        'payment_status' => PaymentStatus::Pending,
        'check_in_status' => CheckInStatus::Unclaimed,
        'qr_token' => Booking::generateQrToken(),
    ]);

    BookingItem::create([
        'booking_id' => $pendingBooking->id,
        'court_slot_id' => $pastSlot->id,
        'price_locked' => 100000,
    ]);

    // Run the artisan command
    $this->artisan('bookings:mark-completed')
        ->expectsOutputToContain('Marked 1 elapsed booking(s) as completed.')
        ->assertSuccessful();

    expect($elapsedBooking->fresh()->payment_status)->toBe(PaymentStatus::Completed)
        ->and($futureBooking->fresh()->payment_status)->toBe(PaymentStatus::Paid)
        ->and($activeBooking->fresh()->payment_status)->toBe(PaymentStatus::Paid)
        ->and($pendingBooking->fresh()->payment_status)->toBe(PaymentStatus::Pending);
});
