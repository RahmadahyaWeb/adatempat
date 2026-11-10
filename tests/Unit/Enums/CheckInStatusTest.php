<?php

declare(strict_types=1);

use App\Enums\CheckInStatus;

test('CheckInStatus enum contains expected string values', function () {
    expect(CheckInStatus::Unclaimed->value)->toBe('unclaimed')
        ->and(CheckInStatus::CheckedIn->value)->toBe('checked_in');
});

test('CheckInStatus values list returns all defined statuses', function () {
    expect(CheckInStatus::values())->toBe([
        'unclaimed',
        'checked_in',
    ]);
});
