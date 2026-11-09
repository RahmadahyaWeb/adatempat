<?php

declare(strict_types=1);

use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->superadmin = User::create([
        'name' => 'Platform Superadmin',
        'email' => 'superadmin@adatempat.com',
        'role' => UserRole::Superadmin,
    ]);

    $this->operator = User::create([
        'name' => 'Regular Operator',
        'email' => 'operator@adatempat.com',
        'role' => UserRole::VenueOperator,
    ]);

    $this->player = User::create([
        'name' => 'Regular Player',
        'email' => 'player@adatempat.com',
        'role' => UserRole::Player,
    ]);
});

test('unauthenticated and non-superadmin users are forbidden from admin routes', function () {
    $this->postJson('/admin/venues', [
        'name' => 'Forbidden Venue',
    ])->assertStatus(401);

    $this->actingAs($this->player)->postJson('/admin/venues', [
        'name' => 'Forbidden Venue',
    ])->assertStatus(403)
        ->assertJson([
            'status' => 'error',
            'message' => 'Access denied. Superadmin role required.',
        ]);

    $this->actingAs($this->operator)->postJson('/admin/venues', [
        'name' => 'Forbidden Venue',
    ])->assertStatus(403)
        ->assertJson([
            'status' => 'error',
            'message' => 'Access denied. Superadmin role required.',
        ]);
});

test('superadmin can onboard a new venue with automatic slug generation', function () {
    $response = $this->actingAs($this->superadmin)->postJson('/admin/venues', [
        'name' => 'Gelora Bung Karno Tennis Center',
        'city' => 'Jakarta Pusat',
        'address' => 'Jl. Pintu Satu Senayan',
        'phone' => '0215734070',
        'description' => 'World class tennis facility',
        'facilities' => ['shower', 'locker', 'parking', 'pro_shop'],
        'booking_horizon_days' => 30,
        'allow_reschedule' => true,
    ]);

    $response->assertStatus(201)
        ->assertJson([
            'status' => 'success',
            'message' => 'Venue onboarded successfully.',
            'data' => [
                'name' => 'Gelora Bung Karno Tennis Center',
                'slug' => 'gelora-bung-karno-tennis-center',
                'city' => 'Jakarta Pusat',
                'subscription_status' => SubscriptionStatus::Trial->value,
                'is_active' => true,
            ],
        ]);

    $this->assertDatabaseHas('venues', [
        'name' => 'Gelora Bung Karno Tennis Center',
        'slug' => 'gelora-bung-karno-tennis-center',
        'city' => 'Jakarta Pusat',
    ]);
});

test('venue onboarding validates mandatory fields and unique slug', function () {
    Venue::create([
        'name' => 'Existing Venue',
        'slug' => 'duplicate-slug',
        'city' => 'Bandung',
        'address' => 'Jl. Riau No. 1',
    ]);

    $response = $this->actingAs($this->superadmin)->postJson('/admin/venues', [
        'name' => '',
        'slug' => 'duplicate-slug',
        'city' => '',
        'address' => '',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'slug', 'city', 'address']);
});

test('superadmin can toggle SaaS subscription status and expiration', function () {
    $venue = Venue::create([
        'name' => 'Surabaya Arena',
        'slug' => 'surabaya-arena',
        'city' => 'Surabaya',
        'address' => 'Jl. Mayjen Sungkono',
        'subscription_status' => SubscriptionStatus::Trial,
    ]);

    // Update to active with expiration date
    $response = $this->actingAs($this->superadmin)->patchJson("/admin/venues/{$venue->id}/subscription", [
        'subscription_status' => 'active',
        'subscription_expires_at' => '2026-12-31 23:59:59',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'message' => 'Venue subscription updated successfully.',
            'data' => [
                'subscription_status' => 'active',
            ],
        ]);

    $venue->refresh();
    expect($venue->subscription_status)->toBe(SubscriptionStatus::Active)
        ->and($venue->hasActiveSubscription())->toBeTrue();

    // Suspend subscription
    $suspendResponse = $this->actingAs($this->superadmin)->patchJson("/admin/venues/{$venue->id}/subscription", [
        'subscription_status' => 'suspended',
    ]);

    $suspendResponse->assertStatus(200);
    $venue->refresh();
    expect($venue->subscription_status)->toBe(SubscriptionStatus::Suspended)
        ->and($venue->hasActiveSubscription())->toBeFalse();
});
