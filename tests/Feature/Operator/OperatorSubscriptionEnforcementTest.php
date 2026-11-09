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
    $this->suspendedVenue = Venue::create([
        'name' => 'Suspended Sports Hub',
        'slug' => 'suspended-sports-hub',
        'city' => 'Bandung',
        'address' => 'Jl. Dago No. 100',
        'booking_horizon_days' => 14,
        'allow_reschedule' => true,
        'is_active' => false,
        'subscription_status' => SubscriptionStatus::Suspended,
        'subscription_expires_at' => now()->subDays(5),
    ]);

    $this->suspendedOperator = User::create([
        'name' => 'Suspended Operator',
        'email' => 'operator@suspendedhub.com',
        'role' => UserRole::VenueOperator,
        'venue_id' => $this->suspendedVenue->id,
    ]);

    $this->court = Court::create([
        'venue_id' => $this->suspendedVenue->id,
        'name' => 'Dago Court 1',
        'sport_type' => SportType::Futsal,
        'opening_time' => '08:00:00',
        'closing_time' => '23:00:00',
        'default_hourly_rate' => 200000,
        'is_active' => true,
    ]);
});

test('operator of suspended venue retains read-only access to venue and courts', function () {
    // Can view venue
    $venueResponse = $this->actingAs($this->suspendedOperator)->getJson('/operator/venue');
    $venueResponse->assertStatus(200)
        ->assertJsonFragment(['name' => 'Suspended Sports Hub']);

    // Can view courts list
    $courtsResponse = $this->actingAs($this->suspendedOperator)->getJson('/operator/courts');
    $courtsResponse->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonFragment(['name' => 'Dago Court 1']);

    // Can view single court details
    $singleResponse = $this->actingAs($this->suspendedOperator)->getJson("/operator/courts/{$this->court->id}");
    $singleResponse->assertStatus(200)
        ->assertJsonFragment(['name' => 'Dago Court 1']);
});

test('operator of suspended venue is blocked from updating venue profile with renewal notice', function () {
    $response = $this->actingAs($this->suspendedOperator)->putJson('/operator/venue', [
        'name' => 'Attempted Name Change',
        'city' => 'Bandung',
        'address' => 'Jl. Dago No. 100',
        'booking_horizon_days' => 30,
        'allow_reschedule' => true,
    ]);

    $response->assertStatus(403)
        ->assertJson([
            'status' => 'error',
            'code' => 'SUBSCRIPTION_INACTIVE',
            'message' => 'Venue subscription is suspended or expired. Please contact administration for renewal.',
        ]);
});

test('operator of suspended venue is blocked from creating courts with renewal notice', function () {
    $response = $this->actingAs($this->suspendedOperator)->postJson('/operator/courts', [
        'name' => 'New Court Under Suspended Venue',
        'sport_type' => 'futsal',
        'opening_time' => '08:00',
        'closing_time' => '22:00',
        'default_hourly_rate' => 200000,
    ]);

    $response->assertStatus(403)
        ->assertJson([
            'status' => 'error',
            'code' => 'SUBSCRIPTION_INACTIVE',
            'message' => 'Venue subscription is suspended or expired. Please contact administration for renewal.',
        ]);
});

test('operator of suspended venue is blocked from updating court with renewal notice', function () {
    $response = $this->actingAs($this->suspendedOperator)->putJson("/operator/courts/{$this->court->id}", [
        'name' => 'Updated Dago Court 1',
        'sport_type' => 'futsal',
        'opening_time' => '08:00',
        'closing_time' => '23:00',
        'default_hourly_rate' => 220000,
    ]);

    $response->assertStatus(403)
        ->assertJson([
            'status' => 'error',
            'code' => 'SUBSCRIPTION_INACTIVE',
            'message' => 'Venue subscription is suspended or expired. Please contact administration for renewal.',
        ]);
});

test('operator of suspended venue is blocked from deleting court with renewal notice', function () {
    $response = $this->actingAs($this->suspendedOperator)->deleteJson("/operator/courts/{$this->court->id}");

    $response->assertStatus(403)
        ->assertJson([
            'status' => 'error',
            'code' => 'SUBSCRIPTION_INACTIVE',
            'message' => 'Venue subscription is suspended or expired. Please contact administration for renewal.',
        ]);
});

test('operator of inactive venue is blocked from mutations with renewal notice', function () {
    $this->suspendedVenue->update([
        'is_active' => false,
        'subscription_status' => SubscriptionStatus::Active,
        'subscription_expires_at' => now()->addMonth(),
    ]);

    $response = $this->actingAs($this->suspendedOperator)->putJson('/operator/venue', [
        'name' => 'Inactive Venue Update Attempt',
        'city' => 'Bandung',
        'address' => 'Jl. Dago No. 100',
        'booking_horizon_days' => 30,
        'allow_reschedule' => true,
    ]);

    $response->assertStatus(403)
        ->assertJson([
            'status' => 'error',
            'code' => 'SUBSCRIPTION_INACTIVE',
        ]);
});

test('operator of expired subscription venue is blocked from mutations', function () {
    $this->suspendedVenue->update([
        'subscription_status' => SubscriptionStatus::Expired,
        'subscription_expires_at' => now()->subDay(),
    ]);

    $response = $this->actingAs($this->suspendedOperator)->putJson('/operator/venue', [
        'name' => 'Expired Venue Update',
        'city' => 'Bandung',
        'address' => 'Jl. Dago No. 100',
        'booking_horizon_days' => 30,
        'allow_reschedule' => true,
    ]);

    $response->assertStatus(403)
        ->assertJson([
            'status' => 'error',
            'code' => 'SUBSCRIPTION_INACTIVE',
        ]);
});

test('superadmin can mutate courts even if venue subscription is suspended', function () {
    $superadmin = User::create([
        'name' => 'Superadmin Admin',
        'email' => 'superadmin@adatempat.com',
        'role' => UserRole::Superadmin,
    ]);

    $response = $this->actingAs($superadmin)->putJson("/operator/courts/{$this->court->id}", [
        'name' => 'Superadmin Override Suspended Court',
        'sport_type' => 'futsal',
        'opening_time' => '08:00',
        'closing_time' => '23:00',
        'default_hourly_rate' => 210000,
        'is_active' => true,
    ]);

    $response->assertStatus(200);
    expect($this->court->fresh()->name)->toBe('Superadmin Override Suspended Court');
});
