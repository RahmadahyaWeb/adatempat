<?php

declare(strict_types=1);

use App\Enums\SlotStatus;
use App\Enums\SportType;
use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Models\Court;
use App\Models\CourtSlot;
use App\Models\User;
use App\Models\Venue;
use App\Policies\CourtSlotPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->venueA = Venue::create([
        'name' => 'Venue Alpha',
        'slug' => 'venue-alpha',
        'city' => 'Jakarta Selatan',
        'address' => 'Jl. Radio Dalam No. 1',
        'is_active' => true,
        'subscription_status' => SubscriptionStatus::Active,
    ]);

    $this->operatorA = User::create([
        'name' => 'Operator Alpha',
        'email' => 'alpha@venue.com',
        'role' => UserRole::VenueOperator,
        'venue_id' => $this->venueA->id,
    ]);

    $this->courtA = Court::create([
        'venue_id' => $this->venueA->id,
        'name' => 'Court A',
        'sport_type' => SportType::Badminton,
        'opening_time' => '08:00:00',
        'closing_time' => '22:00:00',
        'default_hourly_rate' => 100000,
        'is_active' => true,
    ]);

    $this->slotA = CourtSlot::create([
        'court_id' => $this->courtA->id,
        'date' => '2026-11-10',
        'start_time' => '08:00:00',
        'end_time' => '09:00:00',
        'price' => 100000,
        'status' => SlotStatus::Available,
    ]);

    $this->venueB = Venue::create([
        'name' => 'Venue Beta',
        'slug' => 'venue-beta',
        'city' => 'Jakarta Barat',
        'address' => 'Jl. Panjang No. 2',
        'is_active' => true,
        'subscription_status' => SubscriptionStatus::Active,
    ]);

    $this->operatorB = User::create([
        'name' => 'Operator Beta',
        'email' => 'beta@venue.com',
        'role' => UserRole::VenueOperator,
        'venue_id' => $this->venueB->id,
    ]);

    $this->player = User::create([
        'name' => 'Player One',
        'email' => 'player@example.com',
        'role' => UserRole::Player,
    ]);

    $this->policy = new CourtSlotPolicy;
});

test('operator can block and unblock slots in their assigned venue', function () {
    expect($this->policy->block($this->operatorA, $this->slotA))->toBeTrue()
        ->and($this->policy->unblock($this->operatorA, $this->slotA))->toBeTrue();
});

test('operator cannot block or unblock slots in another venue', function () {
    expect($this->policy->block($this->operatorB, $this->slotA))->toBeFalse()
        ->and($this->policy->unblock($this->operatorB, $this->slotA))->toBeFalse();
});

test('player cannot block or unblock court slots', function () {
    expect($this->policy->block($this->player, $this->slotA))->toBeFalse()
        ->and($this->policy->unblock($this->player, $this->slotA))->toBeFalse();
});
