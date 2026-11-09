<?php

declare(strict_types=1);

use App\Enums\SlotStatus;
use App\Models\Court;
use App\Models\CourtSlot;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

test('court slot model casts attributes correctly', function () {
    $slot = new CourtSlot([
        'court_id' => 1,
        'date' => '2026-11-15',
        'start_time' => '09:00:00',
        'end_time' => '10:00:00',
        'price' => 125000,
        'status' => 'held',
        'locked_until' => '2026-11-15 09:10:00',
        'block_reason' => 'Maintenance',
    ]);

    expect($slot->date)->toBeInstanceOf(Carbon::class)
        ->and($slot->status)->toBe(SlotStatus::Held)
        ->and($slot->price)->toBe(125000)
        ->and($slot->locked_until)->toBeInstanceOf(Carbon::class)
        ->and($slot->block_reason)->toBe('Maintenance');
});

test('court slot belongs to court relationship', function () {
    $slot = new CourtSlot;

    expect($slot->court())->toBeInstanceOf(BelongsTo::class)
        ->and($slot->court()->getRelated())->toBeInstanceOf(Court::class);
});

test('resolveEffectiveStatus and isBookable dynamically evaluate hold expiration', function () {
    Carbon::setTestNow('2026-11-10 10:00:00');

    // Available slot
    $availableSlot = new CourtSlot(['status' => SlotStatus::Available]);
    expect($availableSlot->resolveEffectiveStatus())->toBe(SlotStatus::Available)
        ->and($availableSlot->isBookable())->toBeTrue();

    // Active hold
    $activeHeldSlot = new CourtSlot([
        'status' => SlotStatus::Held,
        'locked_until' => Carbon::parse('2026-11-10 10:10:00'),
    ]);
    expect($activeHeldSlot->resolveEffectiveStatus())->toBe(SlotStatus::Held)
        ->and($activeHeldSlot->isBookable())->toBeFalse();

    // Expired hold
    $expiredHeldSlot = new CourtSlot([
        'status' => SlotStatus::Held,
        'locked_until' => Carbon::parse('2026-11-10 09:55:00'),
    ]);
    expect($expiredHeldSlot->resolveEffectiveStatus())->toBe(SlotStatus::Available)
        ->and($expiredHeldSlot->isBookable())->toBeTrue();

    // Booked slot
    $bookedSlot = new CourtSlot(['status' => SlotStatus::Booked]);
    expect($bookedSlot->resolveEffectiveStatus())->toBe(SlotStatus::Booked)
        ->and($bookedSlot->isBookable())->toBeFalse();

    // Blocked slot
    $blockedSlot = new CourtSlot(['status' => SlotStatus::Blocked]);
    expect($blockedSlot->resolveEffectiveStatus())->toBe(SlotStatus::Blocked)
        ->and($blockedSlot->isBookable())->toBeFalse();

    Carbon::setTestNow();
});
