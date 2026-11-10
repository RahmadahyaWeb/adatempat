<?php

declare(strict_types=1);

use App\Enums\CheckInStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\User;
use App\Models\Venue;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

test('booking model casts attributes correctly', function () {
    $booking = new Booking([
        'user_id' => 1,
        'venue_id' => 2,
        'booking_code' => 'ADT-20261110-1234',
        'total_amount' => 300000,
        'payment_status' => 'pending',
        'check_in_status' => 'unclaimed',
        'qr_token' => str_repeat('a', 64),
        'qris_expires_at' => '2026-11-10 10:10:00',
        'paid_at' => '2026-11-10 10:05:00',
        'checked_in_at' => '2026-11-10 10:30:00',
        'rescheduled_at' => '2026-11-10 09:00:00',
    ]);

    expect($booking->total_amount)->toBe(300000)
        ->and($booking->payment_status)->toBe(PaymentStatus::Pending)
        ->and($booking->check_in_status)->toBe(CheckInStatus::Unclaimed)
        ->and($booking->qris_expires_at)->toBeInstanceOf(Carbon::class)
        ->and($booking->paid_at)->toBeInstanceOf(Carbon::class)
        ->and($booking->checked_in_at)->toBeInstanceOf(Carbon::class)
        ->and($booking->rescheduled_at)->toBeInstanceOf(Carbon::class);
});

test('booking relationships return correct relations', function () {
    $booking = new Booking;

    expect($booking->user())->toBeInstanceOf(BelongsTo::class)
        ->and($booking->user()->getRelated())->toBeInstanceOf(User::class)
        ->and($booking->venue())->toBeInstanceOf(BelongsTo::class)
        ->and($booking->venue()->getRelated())->toBeInstanceOf(Venue::class)
        ->and($booking->checkedInBy())->toBeInstanceOf(BelongsTo::class)
        ->and($booking->checkedInBy()->getRelated())->toBeInstanceOf(User::class)
        ->and($booking->items())->toBeInstanceOf(HasMany::class)
        ->and($booking->items()->getRelated())->toBeInstanceOf(BookingItem::class);
});

test('booking code generator produces ADT-YYYYMMDD-XXXX format', function () {
    Carbon::setTestNow('2026-11-10 12:00:00');
    $code = Booking::generateBookingCode();

    expect($code)->toMatch('/^ADT-20261110-[A-Z0-9]{4,6}$/');
});
