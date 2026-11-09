<?php

declare(strict_types=1);

use App\Enums\SportType;
use App\Enums\SubscriptionStatus;
use App\Models\Court;
use App\Models\CourtSlot;
use App\Models\Venue;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow('2026-11-10 08:00:00');

    $this->venue = Venue::create([
        'name' => 'Senayan Sports',
        'slug' => 'senayan-sports',
        'city' => 'Jakarta Pusat',
        'address' => 'Jl. Asia Afrika',
        'booking_horizon_days' => 30,
        'allow_reschedule' => true,
        'is_active' => true,
        'subscription_status' => SubscriptionStatus::Active,
    ]);

    $this->court = Court::create([
        'venue_id' => $this->venue->id,
        'name' => 'Badminton Court 1',
        'sport_type' => SportType::Badminton,
        'opening_time' => '08:00:00',
        'closing_time' => '10:00:00', // 2 hours: 08-09, 09-10
        'default_hourly_rate' => 100000,
        'is_active' => true,
    ]);
});

afterEach(function () {
    Carbon::setTestNow();
});

test('slots:generate command generates discrete 60-minute slots up to specified days', function () {
    $this->artisan('slots:generate --days=2 --court='.$this->court->id)
        ->expectsOutputToContain('Generated')
        ->assertSuccessful();

    // 2 hours/day * 3 days (day 0, 1, 2) = 6 slots
    expect(CourtSlot::where('court_id', $this->court->id)->count())->toBe(6);

    // Running again is idempotent and outputs 0 new slots generated
    $this->artisan('slots:generate --days=2 --court='.$this->court->id)
        ->expectsOutputToContain('Generated 0 new slots')
        ->assertSuccessful();

    expect(CourtSlot::where('court_id', $this->court->id)->count())->toBe(6);
});
