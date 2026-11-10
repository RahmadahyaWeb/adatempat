<?php

declare(strict_types=1);

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\CourtSlot;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

test('booking item model casts attributes correctly', function () {
    $item = new BookingItem([
        'booking_id' => 1,
        'court_slot_id' => 2,
        'price_locked' => 150000,
    ]);

    expect($item->price_locked)->toBe(150000)
        ->and($item->price)->toBe(150000);
});

test('booking item belongs to booking and court slot', function () {
    $item = new BookingItem;

    expect($item->booking())->toBeInstanceOf(BelongsTo::class)
        ->and($item->booking()->getRelated())->toBeInstanceOf(Booking::class)
        ->and($item->courtSlot())->toBeInstanceOf(BelongsTo::class)
        ->and($item->courtSlot()->getRelated())->toBeInstanceOf(CourtSlot::class);
});
