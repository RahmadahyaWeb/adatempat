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

beforeEach(function () {
    $this->superadmin = User::create([
        'name' => 'Platform Superadmin',
        'email' => 'superadmin@adatempat.com',
        'role' => UserRole::Superadmin,
    ]);

    $this->venue = Venue::create([
        'name' => 'Mega Sports Hub',
        'slug' => 'mega-sports-hub',
        'city' => 'Jakarta Selatan',
        'address' => 'Jl. Gatot Subroto',
        'subscription_status' => SubscriptionStatus::Trial,
        'subscription_expires_at' => now()->addDays(30),
        'is_active' => true,
    ]);

    $this->operator = User::create([
        'name' => 'Mega Operator',
        'email' => 'operator.mega@adatempat.com',
        'role' => UserRole::VenueOperator,
        'venue_id' => $this->venue->id,
    ]);

    $this->court = Court::create([
        'venue_id' => $this->venue->id,
        'name' => 'Court 1',
        'sport_type' => SportType::Badminton,
        'opening_time' => '07:00:00',
        'closing_time' => '22:00:00',
        'default_hourly_rate' => 100000,
        'is_active' => true,
    ]);
});

test('transitioning subscription status immediately affects public marketplace discovery', function () {
    // 1. Initial State: Venue is in Trial -> discoverable on public marketplace
    $discoveryInitial = $this->getJson('/api/venues');
    $discoveryInitial->assertStatus(200);
    $slugs = collect($discoveryInitial->json('data'))->pluck('slug')->all();
    expect($slugs)->toContain('mega-sports-hub');

    $this->getJson('/api/venues/mega-sports-hub')->assertStatus(200);

    // 2. Superadmin suspends the venue subscription
    $suspendResponse = $this->actingAs($this->superadmin)->patchJson("/admin/venues/{$this->venue->id}/subscription", [
        'subscription_status' => SubscriptionStatus::Suspended->value,
    ]);
    $suspendResponse->assertStatus(200);

    // Immediate marketplace exclusion
    $discoverySuspended = $this->getJson('/api/venues');
    $suspendedSlugs = collect($discoverySuspended->json('data'))->pluck('slug')->all();
    expect($suspendedSlugs)->not->toContain('mega-sports-hub');

    $this->getJson('/api/venues/mega-sports-hub')->assertStatus(404);
    $this->getJson("/api/courts/{$this->court->id}/slots?date=".now()->addDay()->toDateString())
        ->assertStatus(404);

    // 3. Superadmin sets status to expired
    $this->actingAs($this->superadmin)->patchJson("/admin/venues/{$this->venue->id}/subscription", [
        'subscription_status' => SubscriptionStatus::Expired->value,
    ])->assertStatus(200);

    $discoveryExpired = $this->getJson('/api/venues');
    expect(collect($discoveryExpired->json('data'))->pluck('slug')->all())->not->toContain('mega-sports-hub');
    $this->getJson('/api/venues/mega-sports-hub')->assertStatus(404);

    // 4. Superadmin restores to active with future expiration
    $this->actingAs($this->superadmin)->patchJson("/admin/venues/{$this->venue->id}/subscription", [
        'subscription_status' => SubscriptionStatus::Active->value,
        'subscription_expires_at' => now()->addMonths(12)->toDateTimeString(),
    ])->assertStatus(200);

    // Immediate marketplace inclusion
    $discoveryActive = $this->getJson('/api/venues');
    $activeSlugs = collect($discoveryActive->json('data'))->pluck('slug')->all();
    expect($activeSlugs)->toContain('mega-sports-hub');
    $this->getJson('/api/venues/mega-sports-hub')->assertStatus(200);

    // 5. Expiration timestamp in the past immediately excludes active venue
    $this->actingAs($this->superadmin)->patchJson("/admin/venues/{$this->venue->id}/subscription", [
        'subscription_status' => SubscriptionStatus::Active->value,
        'subscription_expires_at' => now()->subMinute()->toDateTimeString(),
    ])->assertStatus(200);

    $discoveryPastExpiry = $this->getJson('/api/venues');
    expect(collect($discoveryPastExpiry->json('data'))->pluck('slug')->all())->not->toContain('mega-sports-hub');
    $this->getJson('/api/venues/mega-sports-hub')->assertStatus(404);
});

test('transitioning subscription status immediately affects operator write permissions while retaining read access', function () {
    // 1. Initial state (Trial): operator write mutations succeed
    $createCourtResponse = $this->actingAs($this->operator)->postJson('/operator/courts', [
        'name' => 'Court 2 Padel',
        'sport_type' => SportType::Padel->value,
        'opening_time' => '08:00',
        'closing_time' => '22:00',
        'default_hourly_rate' => 200000,
    ]);
    $createCourtResponse->assertStatus(201);

    // 2. Superadmin suspends the venue
    $this->actingAs($this->superadmin)->patchJson("/admin/venues/{$this->venue->id}/subscription", [
        'subscription_status' => SubscriptionStatus::Suspended->value,
    ])->assertStatus(200);

    // Read access is retained
    $this->actingAs($this->operator)->getJson('/operator/venue')
        ->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'data' => [
                'name' => 'Mega Sports Hub',
            ],
        ]);

    $this->actingAs($this->operator)->getJson('/operator/courts')
        ->assertStatus(200);

    // Write access is blocked with 403 SUBSCRIPTION_INACTIVE
    $blockedCourtCreation = $this->actingAs($this->operator)->postJson('/operator/courts', [
        'name' => 'Court 3 Futsal',
        'sport_type' => SportType::Futsal->value,
        'opening_time' => '08:00',
        'closing_time' => '22:00',
        'default_hourly_rate' => 150000,
    ]);

    $blockedCourtCreation->assertStatus(403)
        ->assertJson([
            'status' => 'error',
            'code' => 'SUBSCRIPTION_INACTIVE',
        ]);

    $blockedVenueUpdate = $this->actingAs($this->operator)->putJson('/operator/venue', [
        'name' => 'Updated Venue Name',
        'city' => 'Jakarta Selatan',
        'address' => 'Jl. Gatot Subroto No. 99',
    ]);

    $blockedVenueUpdate->assertStatus(403)
        ->assertJson([
            'status' => 'error',
            'code' => 'SUBSCRIPTION_INACTIVE',
        ]);

    // 3. Superadmin reactivates subscription
    $this->actingAs($this->superadmin)->patchJson("/admin/venues/{$this->venue->id}/subscription", [
        'subscription_status' => SubscriptionStatus::Active->value,
        'subscription_expires_at' => now()->addMonths(6)->toDateTimeString(),
    ])->assertStatus(200);

    // Operator can immediately perform write mutations again
    $reactivatedCourtCreation = $this->actingAs($this->operator)->postJson('/operator/courts', [
        'name' => 'Court 3 Futsal',
        'sport_type' => SportType::Futsal->value,
        'opening_time' => '08:00',
        'closing_time' => '22:00',
        'default_hourly_rate' => 150000,
    ]);

    $reactivatedCourtCreation->assertStatus(201);
});
