<?php

declare(strict_types=1);

use App\Enums\DayType;
use Carbon\Carbon;

test('DayType enum contains expected string values', function () {
    expect(DayType::Weekday->value)->toBe('weekday')
        ->and(DayType::Weekend->value)->toBe('weekend');
});

test('DayType fromCarbon correctly resolves weekdays and weekends', function () {
    // 2026-11-09 is Monday (weekday)
    $monday = Carbon::parse('2026-11-09');
    expect(DayType::fromCarbon($monday))->toBe(DayType::Weekday);

    // 2026-11-13 is Friday (weekday)
    $friday = Carbon::parse('2026-11-13');
    expect(DayType::fromCarbon($friday))->toBe(DayType::Weekday);

    // 2026-11-14 is Saturday (weekend)
    $saturday = Carbon::parse('2026-11-14');
    expect(DayType::fromCarbon($saturday))->toBe(DayType::Weekend);

    // 2026-11-15 is Sunday (weekend)
    $sunday = Carbon::parse('2026-11-15');
    expect(DayType::fromCarbon($sunday))->toBe(DayType::Weekend);
});
