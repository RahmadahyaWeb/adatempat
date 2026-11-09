<?php

declare(strict_types=1);

use App\Enums\SportType;
use App\Models\Court;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

test('court model casts attributes correctly', function () {
    $court = new Court([
        'name' => 'Court 1 - Vinyl',
        'sport_type' => 'badminton',
        'opening_time' => '07:00:00',
        'closing_time' => '23:00:00',
        'default_hourly_rate' => '150000',
        'is_active' => 1,
    ]);

    expect($court->sport_type)->toBe(SportType::Badminton)
        ->and($court->default_hourly_rate)->toBe(150000)
        ->and($court->is_active)->toBeTrue();
});

test('court belongs to venue relationship', function () {
    $court = new Court;

    expect($court->venue())->toBeInstanceOf(BelongsTo::class);
});
