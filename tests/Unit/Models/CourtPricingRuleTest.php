<?php

declare(strict_types=1);

use App\Enums\DayType;
use App\Models\Court;
use App\Models\CourtPricingRule;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

test('court pricing rule model casts attributes correctly', function () {
    $rule = new CourtPricingRule([
        'court_id' => 1,
        'day_type' => 'weekend',
        'start_time' => '08:00:00',
        'end_time' => '12:00:00',
        'price_per_hour' => 150000,
        'tier_name' => 'Weekend Morning',
    ]);

    expect($rule->day_type)->toBe(DayType::Weekend)
        ->and($rule->price_per_hour)->toBe(150000);
});

test('court pricing rule belongs to court relationship', function () {
    $rule = new CourtPricingRule;

    expect($rule->court())->toBeInstanceOf(BelongsTo::class)
        ->and($rule->court()->getRelated())->toBeInstanceOf(Court::class);
});
