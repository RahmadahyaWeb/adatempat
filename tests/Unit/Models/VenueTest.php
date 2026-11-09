<?php

declare(strict_types=1);

use App\Enums\SubscriptionStatus;
use App\Models\Venue;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\HasMany;

test('venue model casts attributes correctly', function () {
    $venue = new Venue([
        'name' => 'Smash Court Arena',
        'slug' => 'smash-court-arena',
        'city' => 'Jakarta Selatan',
        'address' => 'Jl. Kemang Raya No. 10',
        'facilities' => ['shower', 'parking', 'canteen'],
        'booking_horizon_days' => '45',
        'allow_reschedule' => 1,
        'is_active' => 1,
        'subscription_status' => 'active',
        'subscription_expires_at' => '2026-12-31 23:59:59',
    ]);

    expect($venue->facilities)->toBe(['shower', 'parking', 'canteen'])
        ->and($venue->booking_horizon_days)->toBe(45)
        ->and($venue->allow_reschedule)->toBeTrue()
        ->and($venue->is_active)->toBeTrue()
        ->and($venue->subscription_status)->toBe(SubscriptionStatus::Active)
        ->and($venue->subscription_expires_at)->toBeInstanceOf(Carbon::class);
});

test('venue hasActiveSubscription evaluates trial, active, and expiration dates', function () {
    $trialVenue = new Venue([
        'subscription_status' => SubscriptionStatus::Trial,
        'subscription_expires_at' => null,
    ]);
    expect($trialVenue->hasActiveSubscription())->toBeTrue();

    $activeVenue = new Venue([
        'subscription_status' => SubscriptionStatus::Active,
        'subscription_expires_at' => now()->addMonth(),
    ]);
    expect($activeVenue->hasActiveSubscription())->toBeTrue();

    $expiredDateVenue = new Venue([
        'subscription_status' => SubscriptionStatus::Active,
        'subscription_expires_at' => now()->subDay(),
    ]);
    expect($expiredDateVenue->hasActiveSubscription())->toBeFalse();

    $suspendedVenue = new Venue([
        'subscription_status' => SubscriptionStatus::Suspended,
        'subscription_expires_at' => now()->addMonth(),
    ]);
    expect($suspendedVenue->hasActiveSubscription())->toBeFalse();

    $expiredStatusVenue = new Venue([
        'subscription_status' => SubscriptionStatus::Expired,
        'subscription_expires_at' => null,
    ]);
    expect($expiredStatusVenue->hasActiveSubscription())->toBeFalse();
});

test('venue relationships return correct relations', function () {
    $venue = new Venue;

    expect($venue->courts())->toBeInstanceOf(HasMany::class)
        ->and($venue->operators())->toBeInstanceOf(HasMany::class);
});
