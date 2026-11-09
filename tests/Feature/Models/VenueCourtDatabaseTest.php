<?php

declare(strict_types=1);

use App\Enums\SportType;
use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Models\Court;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('venue and court can be persisted, queried, and soft deleted', function () {
    $venue = Venue::create([
        'name' => 'Senayan Racket Hub',
        'slug' => 'senayan-racket-hub',
        'city' => 'Jakarta Pusat',
        'address' => 'Jl. Asia Afrika No. 1',
        'phone' => '081234567890',
        'description' => 'Premier indoor racket hub',
        'facilities' => ['wifi', 'locker', 'parking'],
        'booking_horizon_days' => 30,
        'allow_reschedule' => true,
        'is_active' => true,
        'subscription_status' => SubscriptionStatus::Trial,
        'subscription_expires_at' => now()->addDays(14),
    ]);

    $court = Court::create([
        'venue_id' => $venue->id,
        'name' => 'Badminton Court A',
        'sport_type' => SportType::Badminton,
        'opening_time' => '06:00:00',
        'closing_time' => '23:00:00',
        'default_hourly_rate' => 120000,
        'is_active' => true,
    ]);

    expect($venue->exists)->toBeTrue()
        ->and($court->exists)->toBeTrue()
        ->and($court->venue->id)->toBe($venue->id);

    // Soft deletion test on Court
    $court->delete();
    expect(Court::find($court->id))->toBeNull()
        ->and(Court::withTrashed()->find($court->id))->not->toBeNull()
        ->and(Court::withTrashed()->find($court->id)->trashed())->toBeTrue();

    // Soft deletion test on Venue
    $venue->delete();
    expect(Venue::find($venue->id))->toBeNull()
        ->and(Venue::withTrashed()->find($venue->id))->not->toBeNull()
        ->and(Venue::withTrashed()->find($venue->id)->trashed())->toBeTrue();
});

test('venue operator user has relationship with venue', function () {
    $venue = Venue::create([
        'name' => 'Padel Club Kemang',
        'slug' => 'padel-club-kemang',
        'city' => 'Jakarta Selatan',
        'address' => 'Jl. Bangka No. 5',
        'booking_horizon_days' => 14,
        'allow_reschedule' => true,
        'is_active' => true,
        'subscription_status' => SubscriptionStatus::Active,
    ]);

    $operator = User::create([
        'name' => 'Operator Kemang',
        'email' => 'operator@kemangpadel.com',
        'role' => UserRole::VenueOperator,
        'venue_id' => $venue->id,
    ]);

    expect($operator->venue)->not->toBeNull()
        ->and($operator->venue->id)->toBe($venue->id)
        ->and($venue->operators)->toHaveCount(1)
        ->and($venue->operators->first()->id)->toBe($operator->id);
});
