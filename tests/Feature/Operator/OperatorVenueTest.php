<?php

declare(strict_types=1);

use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('operator can view their assigned venue profile', function () {
    $venue = Venue::create([
        'name' => 'Senayan Sport Arena',
        'slug' => 'senayan-sport-arena',
        'city' => 'Jakarta Pusat',
        'address' => 'Jl. Asia Afrika',
        'phone' => '081298765432',
        'description' => 'Top badminton venue',
        'facilities' => ['parking', 'canteen'],
        'booking_horizon_days' => 30,
        'allow_reschedule' => true,
        'is_active' => true,
        'subscription_status' => SubscriptionStatus::Active,
    ]);

    $operator = User::create([
        'name' => 'Budi Operator',
        'email' => 'budi@senayan.com',
        'role' => UserRole::VenueOperator,
        'venue_id' => $venue->id,
    ]);

    $response = $this->actingAs($operator)->getJson('/operator/venue');

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'data' => [
                'id' => $venue->id,
                'name' => 'Senayan Sport Arena',
                'slug' => 'senayan-sport-arena',
                'city' => 'Jakarta Pusat',
                'booking_horizon_days' => 30,
                'allow_reschedule' => true,
            ],
        ]);
});

test('operator can update their assigned venue profile', function () {
    $venue = Venue::create([
        'name' => 'Old Arena Name',
        'slug' => 'old-arena-name',
        'city' => 'Jakarta Selatan',
        'address' => 'Jl. Fatmawati',
        'booking_horizon_days' => 14,
        'allow_reschedule' => false,
        'is_active' => true,
        'subscription_status' => SubscriptionStatus::Active,
    ]);

    $operator = User::create([
        'name' => 'Siti Operator',
        'email' => 'siti@arena.com',
        'role' => UserRole::VenueOperator,
        'venue_id' => $venue->id,
    ]);

    $response = $this->actingAs($operator)->putJson('/operator/venue', [
        'name' => 'Updated Arena Name',
        'city' => 'Jakarta Selatan',
        'address' => 'Jl. Fatmawati No. 88',
        'phone' => '081122334455',
        'description' => 'Newly renovated courts',
        'facilities' => ['parking', 'shower', 'ac'],
        'booking_horizon_days' => 60,
        'allow_reschedule' => true,
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'message' => 'Venue profile updated successfully.',
            'data' => [
                'name' => 'Updated Arena Name',
                'address' => 'Jl. Fatmawati No. 88',
                'booking_horizon_days' => 60,
                'allow_reschedule' => true,
            ],
        ]);

    $venue->refresh();
    expect($venue->name)->toBe('Updated Arena Name')
        ->and($venue->booking_horizon_days)->toBe(60)
        ->and($venue->allow_reschedule)->toBeTrue()
        ->and($venue->facilities)->toBe(['parking', 'shower', 'ac']);
});
