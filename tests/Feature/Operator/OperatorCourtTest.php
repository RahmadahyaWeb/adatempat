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
    $this->venueA = Venue::create([
        'name' => 'Venue Alpha',
        'slug' => 'venue-alpha',
        'city' => 'Jakarta Selatan',
        'address' => 'Jl. Radio Dalam No. 1',
        'booking_horizon_days' => 30,
        'allow_reschedule' => true,
        'is_active' => true,
        'subscription_status' => SubscriptionStatus::Active,
    ]);

    $this->operatorA = User::create([
        'name' => 'Operator Alpha',
        'email' => 'alpha@venue.com',
        'role' => UserRole::VenueOperator,
        'venue_id' => $this->venueA->id,
    ]);

    $this->courtA1 = Court::create([
        'venue_id' => $this->venueA->id,
        'name' => 'Alpha Court 1',
        'sport_type' => SportType::Badminton,
        'opening_time' => '07:00:00',
        'closing_time' => '23:00:00',
        'default_hourly_rate' => 100000,
        'is_active' => true,
    ]);

    $this->venueB = Venue::create([
        'name' => 'Venue Beta',
        'slug' => 'venue-beta',
        'city' => 'Jakarta Barat',
        'address' => 'Jl. Panjang No. 2',
        'booking_horizon_days' => 30,
        'allow_reschedule' => true,
        'is_active' => true,
        'subscription_status' => SubscriptionStatus::Active,
    ]);

    $this->operatorB = User::create([
        'name' => 'Operator Beta',
        'email' => 'beta@venue.com',
        'role' => UserRole::VenueOperator,
        'venue_id' => $this->venueB->id,
    ]);

    $this->courtB1 = Court::create([
        'venue_id' => $this->venueB->id,
        'name' => 'Beta Court 1',
        'sport_type' => SportType::Padel,
        'opening_time' => '08:00:00',
        'closing_time' => '22:00:00',
        'default_hourly_rate' => 250000,
        'is_active' => true,
    ]);
});

test('court creation uses default operational hours if omitted', function () {
    $response = $this->actingAs($this->operatorA)->postJson('/operator/courts', [
        'name' => 'Default Hours Court',
        'sport_type' => 'tennis',
        'default_hourly_rate' => 175000,
    ]);

    $response->assertStatus(201)
        ->assertJson([
            'status' => 'success',
            'data' => [
                'name' => 'Default Hours Court',
                'opening_time' => '06:00:00',
                'closing_time' => '23:00:00',
            ],
        ]);
});

test('partial update validates closing time against existing opening time', function () {
    // Attempt to set closing time earlier than court's opening time (07:00:00)
    $response = $this->actingAs($this->operatorA)->putJson("/operator/courts/{$this->courtA1->id}", [
        'closing_time' => '05:00',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['closing_time']);
});

test('operator only sees courts belonging to their assigned venue', function () {
    $response = $this->actingAs($this->operatorA)->getJson('/operator/courts');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonFragment(['name' => 'Alpha Court 1'])
        ->assertJsonMissing(['name' => 'Beta Court 1']);
});

test('operator can create a court for their assigned venue', function () {
    $response = $this->actingAs($this->operatorA)->postJson('/operator/courts', [
        'name' => 'Alpha Court 2 - Vinyl',
        'sport_type' => 'badminton',
        'opening_time' => '06:00',
        'closing_time' => '22:00',
        'default_hourly_rate' => 150000,
        'is_active' => true,
    ]);

    $response->assertStatus(201)
        ->assertJson([
            'status' => 'success',
            'data' => [
                'name' => 'Alpha Court 2 - Vinyl',
                'sport_type' => 'badminton',
                'default_hourly_rate' => 150000,
            ],
        ]);

    $this->assertDatabaseHas('courts', [
        'venue_id' => $this->venueA->id,
        'name' => 'Alpha Court 2 - Vinyl',
    ]);
});

test('court creation validates sport type and operational hours', function () {
    $response = $this->actingAs($this->operatorA)->postJson('/operator/courts', [
        'name' => 'Invalid Court',
        'sport_type' => 'cricket', // Invalid sport
        'opening_time' => '22:00',
        'closing_time' => '06:00', // Closing before opening
        'default_hourly_rate' => -5000, // Negative rate
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['sport_type', 'closing_time', 'default_hourly_rate']);
});

test('operator can view their own court details', function () {
    $response = $this->actingAs($this->operatorA)->getJson("/operator/courts/{$this->courtA1->id}");

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'data' => [
                'id' => $this->courtA1->id,
                'name' => 'Alpha Court 1',
            ],
        ]);
});

test('operator cannot view court belonging to another venue (cross-venue 403 Forbidden)', function () {
    $response = $this->actingAs($this->operatorA)->getJson("/operator/courts/{$this->courtB1->id}");

    $response->assertStatus(403);
});

test('operator cannot update court belonging to another venue (cross-venue 403 Forbidden)', function () {
    $response = $this->actingAs($this->operatorA)->putJson("/operator/courts/{$this->courtB1->id}", [
        'name' => 'Hijacked Court',
        'sport_type' => 'badminton',
        'opening_time' => '07:00',
        'closing_time' => '23:00',
        'default_hourly_rate' => 100000,
        'is_active' => true,
    ]);

    $response->assertStatus(403);
    expect($this->courtB1->fresh()->name)->toBe('Beta Court 1');
});

test('operator cannot delete court belonging to another venue (cross-venue 403 Forbidden)', function () {
    $response = $this->actingAs($this->operatorA)->deleteJson("/operator/courts/{$this->courtB1->id}");

    $response->assertStatus(403);
    expect(Court::find($this->courtB1->id))->not->toBeNull();
});

test('operator can update their own court details', function () {
    $response = $this->actingAs($this->operatorA)->putJson("/operator/courts/{$this->courtA1->id}", [
        'name' => 'Alpha Court 1 - Renovated',
        'sport_type' => 'badminton',
        'opening_time' => '06:00',
        'closing_time' => '23:00',
        'default_hourly_rate' => 130000,
        'is_active' => false,
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'message' => 'Court updated successfully.',
            'data' => [
                'name' => 'Alpha Court 1 - Renovated',
                'default_hourly_rate' => 130000,
                'is_active' => false,
            ],
        ]);

    expect($this->courtA1->fresh()->name)->toBe('Alpha Court 1 - Renovated')
        ->and($this->courtA1->fresh()->is_active)->toBeFalse();
});

test('operator can soft-delete their own court', function () {
    $response = $this->actingAs($this->operatorA)->deleteJson("/operator/courts/{$this->courtA1->id}");

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'message' => 'Court deleted successfully.',
        ]);

    expect(Court::find($this->courtA1->id))->toBeNull()
        ->and(Court::withTrashed()->find($this->courtA1->id)->trashed())->toBeTrue();
});

test('superadmin can access and mutate court across any venue', function () {
    $superadmin = User::create([
        'name' => 'Superadmin User',
        'email' => 'superadmin@adatempat.com',
        'role' => UserRole::Superadmin,
    ]);

    // View across venue
    $viewResponse = $this->actingAs($superadmin)->getJson("/operator/courts/{$this->courtB1->id}");
    $viewResponse->assertStatus(200)
        ->assertJsonFragment(['name' => 'Beta Court 1']);

    // Update across venue
    $updateResponse = $this->actingAs($superadmin)->putJson("/operator/courts/{$this->courtB1->id}", [
        'name' => 'Beta Court 1 - Superadmin Override',
        'sport_type' => 'padel',
        'opening_time' => '08:00',
        'closing_time' => '22:00',
        'default_hourly_rate' => 260000,
        'is_active' => true,
    ]);

    $updateResponse->assertStatus(200);
    expect($this->courtB1->fresh()->name)->toBe('Beta Court 1 - Superadmin Override');
});
