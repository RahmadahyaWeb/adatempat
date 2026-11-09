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

    // 1. Active venue in Jakarta Selatan with Badminton and Padel
    $this->activeVenueJakarta = Venue::create([
        'name' => 'Senayan Sports Hub',
        'slug' => 'senayan-sports-hub',
        'city' => 'Jakarta Selatan',
        'address' => 'Jl. Senayan No. 10',
        'phone' => '081234567890',
        'description' => 'Premier multi-sport facility in Senayan.',
        'facilities' => ['Parking', 'Locker Room', 'Shower', 'WiFi'],
        'booking_horizon_days' => 14,
        'allow_reschedule' => true,
        'is_active' => true,
        'subscription_status' => SubscriptionStatus::Active,
        'subscription_expires_at' => Carbon::parse('2026-12-31 23:59:59'),
    ]);

    $this->courtBadminton = Court::create([
        'venue_id' => $this->activeVenueJakarta->id,
        'name' => 'Court Badminton 1',
        'sport_type' => SportType::Badminton,
        'default_hourly_rate' => 120000,
        'is_active' => true,
    ]);

    $this->courtPadel = Court::create([
        'venue_id' => $this->activeVenueJakarta->id,
        'name' => 'Court Padel 1',
        'sport_type' => SportType::Padel,
        'default_hourly_rate' => 250000,
        'is_active' => true,
    ]);

    // 2. Active venue in Bandung with Futsal only
    $this->activeVenueBandung = Venue::create([
        'name' => 'Bandung Arena',
        'slug' => 'bandung-arena',
        'city' => 'Bandung',
        'address' => 'Jl. Dago No. 50',
        'phone' => '082233445566',
        'description' => 'High quality futsal center in Dago.',
        'facilities' => ['Parking', 'Canteen'],
        'booking_horizon_days' => 30,
        'allow_reschedule' => true,
        'is_active' => true,
        'subscription_status' => SubscriptionStatus::Trial,
        'subscription_expires_at' => Carbon::parse('2026-11-25 00:00:00'),
    ]);

    $this->courtFutsal = Court::create([
        'venue_id' => $this->activeVenueBandung->id,
        'name' => 'Futsal Vinyl 1',
        'sport_type' => SportType::Futsal,
        'default_hourly_rate' => 180000,
        'is_active' => true,
    ]);

    // 3. Suspended venue in Jakarta Selatan
    $this->suspendedVenue = Venue::create([
        'name' => 'Suspended Sports Club',
        'slug' => 'suspended-sports-club',
        'city' => 'Jakarta Selatan',
        'address' => 'Jl. Radio Dalam No. 99',
        'is_active' => true,
        'subscription_status' => SubscriptionStatus::Suspended,
    ]);

    Court::create([
        'venue_id' => $this->suspendedVenue->id,
        'name' => 'Suspended Court',
        'sport_type' => SportType::Badminton,
        'default_hourly_rate' => 100000,
        'is_active' => true,
    ]);

    // 4. Inactive venue (is_active = false)
    $this->inactiveVenue = Venue::create([
        'name' => 'Inactive Venue',
        'slug' => 'inactive-venue',
        'city' => 'Jakarta Selatan',
        'address' => 'Jl. Fatmawati',
        'is_active' => false,
        'subscription_status' => SubscriptionStatus::Active,
    ]);

    // 5. Expired SaaS subscription venue
    $this->expiredVenue = Venue::create([
        'name' => 'Expired Sub Venue',
        'slug' => 'expired-sub-venue',
        'city' => 'Jakarta Selatan',
        'address' => 'Jl. Kemang',
        'is_active' => true,
        'subscription_status' => SubscriptionStatus::Active,
        'subscription_expires_at' => Carbon::parse('2026-11-01 00:00:00'), // Expired 9 days ago
    ]);
});

afterEach(function () {
    Carbon::setTestNow();
});

test('public discovery returns only active venues with active SaaS subscriptions', function () {
    $response = $this->getJson('/api/venues');

    $response->assertOk()
        ->assertJsonCount(2, 'data');

    $slugs = collect($response->json('data'))->pluck('slug')->all();
    expect($slugs)->toContain('senayan-sports-hub', 'bandung-arena')
        ->and($slugs)->not->toContain('suspended-sports-club', 'inactive-venue', 'expired-sub-venue');
});

test('public discovery includes starting price and supported sports list', function () {
    $response = $this->getJson('/api/venues');

    $response->assertOk();

    $senayan = collect($response->json('data'))->firstWhere('slug', 'senayan-sports-hub');
    expect($senayan)->not->toBeNull()
        ->and($senayan['name'])->toBe('Senayan Sports Hub')
        ->and($senayan['city'])->toBe('Jakarta Selatan')
        ->and($senayan['starting_price'])->toBe(120000)
        ->and($senayan['sports'])->toContain('badminton', 'padel')
        ->and($senayan['facilities'])->toContain('Parking', 'WiFi');
});

test('public discovery filters venues by city', function () {
    $response = $this->getJson('/api/venues?city=Bandung');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.slug', 'bandung-arena')
        ->assertJsonPath('data.0.city', 'Bandung');
});

test('public discovery filters venues by sport type', function () {
    $response = $this->getJson('/api/venues?sport_type=futsal');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.slug', 'bandung-arena');

    $responseBadminton = $this->getJson('/api/venues?sport_type=badminton');
    $responseBadminton->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.slug', 'senayan-sports-hub');
});

test('public discovery filters venues by both city and sport type concurrently', function () {
    $matchResponse = $this->getJson('/api/venues?city=Jakarta%20Selatan&sport_type=padel');
    $matchResponse->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.slug', 'senayan-sports-hub');

    $noMatchResponse = $this->getJson('/api/venues?city=Bandung&sport_type=padel');
    $noMatchResponse->assertOk()
        ->assertJsonCount(0, 'data');
});
