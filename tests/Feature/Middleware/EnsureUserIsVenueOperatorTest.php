<?php

declare(strict_types=1);

use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Http\Middleware\EnsureUserIsVenueOperator;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

beforeEach(function () {
    Route::middleware(['web', 'auth', EnsureUserIsVenueOperator::class])
        ->get('/operator/test-scope', function () {
            return response()->json(['status' => 'granted']);
        });
});

test('unauthenticated user cannot access operator backoffice', function () {
    $response = $this->getJson('/operator/test-scope');

    $response->assertStatus(401);
});

test('player role cannot access operator backoffice', function () {
    $player = User::create([
        'name' => 'Player One',
        'email' => 'player@example.com',
        'role' => UserRole::Player,
    ]);

    $response = $this->actingAs($player)->getJson('/operator/test-scope');

    $response->assertStatus(403)
        ->assertJson([
            'status' => 'error',
            'message' => 'Access denied. Venue operator role required.',
        ]);
});

test('venue operator without assigned venue is rejected with 403', function () {
    $operator = User::create([
        'name' => 'Unassigned Operator',
        'email' => 'unassigned@example.com',
        'role' => UserRole::VenueOperator,
        'venue_id' => null,
    ]);

    $response = $this->actingAs($operator)->getJson('/operator/test-scope');

    $response->assertStatus(403)
        ->assertJson([
            'status' => 'error',
            'message' => 'Access denied. Operator is not assigned to any venue.',
        ]);
});

test('venue operator with assigned venue is granted access', function () {
    $venue = Venue::create([
        'name' => 'Cilandak Sport Center',
        'slug' => 'cilandak-sport-center',
        'city' => 'Jakarta Selatan',
        'address' => 'Jl. TB Simatupang',
        'subscription_status' => SubscriptionStatus::Active,
    ]);

    $operator = User::create([
        'name' => 'Assigned Operator',
        'email' => 'assigned@example.com',
        'role' => UserRole::VenueOperator,
        'venue_id' => $venue->id,
    ]);

    $response = $this->actingAs($operator)->getJson('/operator/test-scope');

    $response->assertStatus(200)
        ->assertJson(['status' => 'granted']);
});
