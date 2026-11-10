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

test('superadmin can onboard a new venue and link an existing venue operator user account in one step', function () {
    $existingUser = User::create([
        'name' => 'Budi Operator',
        'email' => 'budi.operator@adatempat.com',
        'role' => UserRole::Player,
    ]);

    $response = $this->actingAs($this->superadmin)->postJson('/admin/venues', [
        'name' => 'Kuningan Padel Club',
        'city' => 'Jakarta Selatan',
        'address' => 'Jl. HR Rasuna Said',
        'operator_id' => $existingUser->id,
    ]);

    $response->assertStatus(201)
        ->assertJson([
            'status' => 'success',
            'message' => 'Venue onboarded successfully.',
            'data' => [
                'name' => 'Kuningan Padel Club',
                'slug' => 'kuningan-padel-club',
            ],
        ]);

    $createdVenueId = $response->json('data.id');
    $existingUser->refresh();
    expect($existingUser->venue_id)->toBe($createdVenueId)
        ->and($existingUser->role)->toBe(UserRole::VenueOperator);
});

test('superadmin can onboard a venue and link an operator via email address', function () {
    $response = $this->actingAs($this->superadmin)->postJson('/admin/venues', [
        'name' => 'Pondok Indah Tennis Court',
        'city' => 'Jakarta Selatan',
        'address' => 'Jl. Metro Pondok Indah',
        'operator_email' => 'manager.pitc@adatempat.com',
    ]);

    $response->assertStatus(201);
    $createdVenueId = $response->json('data.id');

    $operator = User::where('email', 'manager.pitc@adatempat.com')->first();
    expect($operator)->not->toBeNull()
        ->and($operator->venue_id)->toBe($createdVenueId)
        ->and($operator->role)->toBe(UserRole::VenueOperator);
});

test('superadmin can list all venues and view individual venue profile', function () {
    Venue::create([
        'name' => 'Alpha Sports Arena',
        'slug' => 'alpha-sports-arena',
        'city' => 'Bandung',
        'address' => 'Jl. Dago',
    ]);

    $indexResponse = $this->actingAs($this->superadmin)->getJson('/admin/venues');
    $indexResponse->assertStatus(200)
        ->assertJsonStructure([
            'status',
            'data' => [
                '*' => ['id', 'name', 'slug', 'city', 'operators', 'courts'],
            ],
        ]);

    $venue = Venue::where('slug', 'alpha-sports-arena')->first();
    $showResponse = $this->actingAs($this->superadmin)->getJson("/admin/venues/{$venue->id}");
    $showResponse->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'data' => [
                'name' => 'Alpha Sports Arena',
                'slug' => 'alpha-sports-arena',
            ],
        ]);
});

test('superadmin can link and unlink operators for a venue', function () {
    $venue = Venue::create([
        'name' => 'Kemang Badminton Hall',
        'slug' => 'kemang-badminton-hall',
        'city' => 'Jakarta Selatan',
        'address' => 'Jl. Bangka Raya',
    ]);

    $staffUser = User::create([
        'name' => 'Staff Member',
        'email' => 'staff.kemang@adatempat.com',
        'role' => UserRole::Player,
    ]);

    // Link operator via user_id
    $linkResponse = $this->actingAs($this->superadmin)->postJson("/admin/venues/{$venue->id}/operators", [
        'user_id' => $staffUser->id,
    ]);

    $linkResponse->assertStatus(201)
        ->assertJson([
            'status' => 'success',
            'message' => 'Venue operator linked successfully.',
            'data' => [
                'id' => $staffUser->id,
                'venue_id' => $venue->id,
                'role' => UserRole::VenueOperator->value,
            ],
        ]);

    $staffUser->refresh();
    expect($staffUser->venue_id)->toBe($venue->id)
        ->and($staffUser->role)->toBe(UserRole::VenueOperator);

    // List operators
    $listResponse = $this->actingAs($this->superadmin)->getJson("/admin/venues/{$venue->id}/operators");
    $listResponse->assertStatus(200);
    expect($listResponse->json('data.0.id'))->toBe($staffUser->id);

    // Unlink operator
    $unlinkResponse = $this->actingAs($this->superadmin)->deleteJson("/admin/venues/{$venue->id}/operators/{$staffUser->id}");
    $unlinkResponse->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'message' => 'Venue operator unlinked successfully.',
        ]);

    $staffUser->refresh();
    expect($staffUser->venue_id)->toBeNull();
});
