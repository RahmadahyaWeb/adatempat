<?php

declare(strict_types=1);

use App\Enums\SportType;
use App\Enums\SubscriptionStatus;
use App\Models\Court;
use App\Models\Venue;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow('2026-11-10 10:00:00');

    $this->venue = Venue::create([
        'name' => 'Elite Sports Arena',
        'slug' => 'elite-sports-arena',
        'city' => 'Surabaya',
        'address' => 'Jl. Mayjen Sungkono No. 88',
        'phone' => '0315551234',
        'description' => 'Olympic standard facilities with indoor air-conditioned badminton and padel.',
        'facilities' => ['AC', 'Locker Room', 'Shower', 'Cafe', 'Pro Shop'],
        'booking_horizon_days' => 21,
        'allow_reschedule' => true,
        'is_active' => true,
        'subscription_status' => SubscriptionStatus::Active,
        'subscription_expires_at' => Carbon::parse('2026-12-31 23:59:59'),
    ]);

    $this->court1 = Court::create([
        'venue_id' => $this->venue->id,
        'name' => 'Court A - Badminton',
        'sport_type' => SportType::Badminton,
        'opening_time' => '07:00:00',
        'closing_time' => '22:00:00',
        'default_hourly_rate' => 150000,
        'is_active' => true,
    ]);

    $this->court2 = Court::create([
        'venue_id' => $this->venue->id,
        'name' => 'Court B - Padel Panoramic',
        'sport_type' => SportType::Padel,
        'opening_time' => '06:00:00',
        'closing_time' => '23:00:00',
        'default_hourly_rate' => 300000,
        'is_active' => true,
    ]);

    // Inactive court under the active venue
    $this->inactiveCourt = Court::create([
        'venue_id' => $this->venue->id,
        'name' => 'Court C - Maintenance',
        'sport_type' => SportType::Badminton,
        'opening_time' => '08:00:00',
        'closing_time' => '20:00:00',
        'default_hourly_rate' => 100000,
        'is_active' => false,
    ]);

    // Suspended venue
    $this->suspendedVenue = Venue::create([
        'name' => 'Suspended Center',
        'slug' => 'suspended-center',
        'city' => 'Surabaya',
        'address' => 'Jl. Basuki Rahmat',
        'is_active' => true,
        'subscription_status' => SubscriptionStatus::Suspended,
    ]);

    // Expired subscription venue
    $this->expiredVenue = Venue::create([
        'name' => 'Expired Center',
        'slug' => 'expired-center',
        'city' => 'Surabaya',
        'address' => 'Jl. Pemuda',
        'is_active' => true,
        'subscription_status' => SubscriptionStatus::Active,
        'subscription_expires_at' => Carbon::parse('2026-11-01 00:00:00'),
    ]);
});

afterEach(function () {
    Carbon::setTestNow();
});

test('public venue profile endpoint returns full details, facilities, and active courts', function () {
    $response = $this->getJson('/api/venues/elite-sports-arena');

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.name', 'Elite Sports Arena')
        ->assertJsonPath('data.city', 'Surabaya')
        ->assertJsonPath('data.address', 'Jl. Mayjen Sungkono No. 88')
        ->assertJsonPath('data.phone', '0315551234')
        ->assertJsonPath('data.booking_horizon_days', 21)
        ->assertJsonPath('data.facilities', ['AC', 'Locker Room', 'Shower', 'Cafe', 'Pro Shop'])
        ->assertJsonPath('data.operational_hours.opening_time', '06:00:00')
        ->assertJsonPath('data.operational_hours.closing_time', '23:00:00');

    $courts = $response->json('data.courts');
    expect($courts)->toHaveCount(2);

    $courtNames = collect($courts)->pluck('name')->all();
    expect($courtNames)->toContain('Court A - Badminton', 'Court B - Padel Panoramic')
        ->and($courtNames)->not->toContain('Court C - Maintenance');
});

test('public venue profile returns 404 when venue is suspended', function () {
    $response = $this->getJson('/api/venues/suspended-center');

    $response->assertNotFound()
        ->assertJsonPath('status', 'error');
});

test('public venue profile returns 404 when venue subscription has expired', function () {
    $response = $this->getJson('/api/venues/expired-center');

    $response->assertNotFound()
        ->assertJsonPath('status', 'error');
});

test('public venue profile returns 404 for unknown slug', function () {
    $response = $this->getJson('/api/venues/non-existent-venue');

    $response->assertNotFound()
        ->assertJsonPath('status', 'error');
});
