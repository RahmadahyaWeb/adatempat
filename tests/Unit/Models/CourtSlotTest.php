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
