<?php

declare(strict_types=1);

use App\Enums\SlotStatus;

test('SlotStatus enum contains expected values', function () {
    expect(SlotStatus::Available->value)->toBe('available')
        ->and(SlotStatus::Held->value)->toBe('held')
        ->and(SlotStatus::Booked->value)->toBe('booked')
        ->and(SlotStatus::Blocked->value)->toBe('blocked');
});

test('SlotStatus canDetermineIfBookable and mutableByPricing', function () {
    expect(SlotStatus::Available->isBookable())->toBeTrue()
        ->and(SlotStatus::Available->isMutableByPricing())->toBeTrue()
        ->and(SlotStatus::Held->isBookable())->toBeFalse()
        ->and(SlotStatus::Held->isMutableByPricing())->toBeFalse()
        ->and(SlotStatus::Booked->isBookable())->toBeFalse()
        ->and(SlotStatus::Booked->isMutableByPricing())->toBeFalse()
        ->and(SlotStatus::Blocked->isBookable())->toBeFalse()
        ->and(SlotStatus::Blocked->isMutableByPricing())->toBeFalse();
});
