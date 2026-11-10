<?php

declare(strict_types=1);

use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Models\User;
use App\Models\Venue;
use App\Services\VenueManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new VenueManagementService;
});

test('onboardVenue creates venue with generated unique slug and default attributes', function () {
    $venue = $this->service->onboardVenue([
        'name' => 'Senayan Padel Club',
        'city' => 'Jakarta Selatan',
        'address' => 'Jl. Asia Afrika',
    ]);

    expect($venue->id)->not->toBeNull()
        ->and($venue->slug)->toBe('senayan-padel-club')
        ->and($venue->is_active)->toBeTrue()
        ->and($venue->subscription_status)->toBe(SubscriptionStatus::Trial);

    // Second venue with identical name generates unique incremental slug
    $venue2 = $this->service->onboardVenue([
        'name' => 'Senayan Padel Club',
        'city' => 'Jakarta Selatan',
        'address' => 'Jl. Asia Afrika No. 2',
    ]);

    expect($venue2->slug)->toBe('senayan-padel-club-1');
});

test('onboardVenue links existing operator user account by id', function () {
    $user = User::create([
        'name' => 'Ahmad Operator',
        'email' => 'ahmad@padel.com',
        'role' => UserRole::Player,
    ]);

    $venue = $this->service->onboardVenue([
        'name' => 'Cilandak Sports Center',
        'city' => 'Jakarta Selatan',
        'address' => 'Jl. TB Simatupang',
    ], operatorId: $user->id);

    $user->refresh();
    expect($user->venue_id)->toBe($venue->id)
        ->and($user->role)->toBe(UserRole::VenueOperator)
        ->and($venue->operators->pluck('id')->all())->toContain($user->id);
});

test('onboardVenue creates and links new operator account by email', function () {
    $venue = $this->service->onboardVenue([
        'name' => 'BSD Badminton Arena',
        'city' => 'Tangerang Selatan',
        'address' => 'Jl. BSD Grand Boulevard',
    ], operatorEmail: 'manager.bsd@adatempat.com');

    $operator = User::where('email', 'manager.bsd@adatempat.com')->first();
    expect($operator)->not->toBeNull()
        ->and($operator->venue_id)->toBe($venue->id)
        ->and($operator->role)->toBe(UserRole::VenueOperator);
});

test('linkOperator and unlinkOperator modify user assignment correctly', function () {
    $venue = Venue::create([
        'name' => 'Kemang Futsal Club',
        'slug' => 'kemang-futsal-club',
        'city' => 'Jakarta Selatan',
        'address' => 'Jl. Kemang Raya',
    ]);

    $user = User::create([
        'name' => 'Rian Operator',
        'email' => 'rian@kemang.com',
        'role' => UserRole::Player,
    ]);

    $linked = $this->service->linkOperator($venue, $user);
    expect($linked->venue_id)->toBe($venue->id)
        ->and($linked->role)->toBe(UserRole::VenueOperator);

    $unlinked = $this->service->unlinkOperator($venue, $user);
    expect($unlinked->venue_id)->toBeNull();
});

test('updateSubscription transitions status and updates expiration timestamp', function () {
    $venue = Venue::create([
        'name' => 'Surabaya Arena',
        'slug' => 'surabaya-arena',
        'city' => 'Surabaya',
        'address' => 'Jl. Mayjen Sungkono',
        'subscription_status' => SubscriptionStatus::Trial,
    ]);

    $futureDate = now()->addMonths(6);
    $updated = $this->service->updateSubscription($venue, SubscriptionStatus::Active, $futureDate);

    expect($updated->subscription_status)->toBe(SubscriptionStatus::Active)
        ->and($updated->subscription_expires_at->toDateTimeString())->toBe($futureDate->toDateTimeString())
        ->and($updated->hasActiveSubscription())->toBeTrue();
});
