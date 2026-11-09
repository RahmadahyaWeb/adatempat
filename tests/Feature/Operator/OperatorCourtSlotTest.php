<?php

declare(strict_types=1);

use App\Enums\DayType;
use App\Enums\SportType;
use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Models\Court;
use App\Models\CourtPricingRule;
use App\Models\CourtSlot;
use App\Models\User;
use App\Models\Venue;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow('2026-11-10 09:00:00'); // Tuesday

    $this->venueA = Venue::create([
        'name' => 'Venue Alpha',
        'slug' => 'venue-alpha',
        'city' => 'Jakarta Selatan',
        'address' => 'Jl. Radio Dalam No. 1',
        'booking_horizon_days' => 14,
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

    $this->courtA = Court::create([
        'venue_id' => $this->venueA->id,
        'name' => 'Alpha Court 1',
        'sport_type' => SportType::Badminton,
        'opening_time' => '08:00:00',
        'closing_time' => '12:00:00', // 4 one-hour blocks: 08-09, 09-10, 10-11, 11-12
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
});

afterEach(function () {
    Carbon::setTestNow();
});

test('first calendar view triggers just-in-time slot generation idempotently', function () {
    CourtPricingRule::create([
        'court_id' => $this->courtA->id,
        'day_type' => DayType::Weekday,
        'start_time' => '08:00:00',
        'end_time' => '10:00:00',
        'price_per_hour' => 150000,
        'tier_name' => 'Morning Peak',
    ]);

    // Query for 2026-11-12 (Thursday, 2 days ahead, within horizon)
    expect(CourtSlot::where('court_id', $this->courtA->id)->where('date', '2026-11-12')->count())->toBe(0);

    $response = $this->actingAs($this->operatorA)
        ->getJson(route('operator.courts.slots.index', [
            'court' => $this->courtA,
            'date' => '2026-11-12',
        ]));

    $response->assertOk()
        ->assertJsonCount(4, 'data')
        ->assertJsonPath('data.0.start_time', '08:00:00')
        ->assertJsonPath('data.0.price', 150000)
        ->assertJsonPath('data.2.start_time', '10:00:00')
        ->assertJsonPath('data.2.price', 100000); // Default fallback

    // 4 slots persisted in database
    expect(CourtSlot::where('court_id', $this->courtA->id)->where('date', '2026-11-12')->count())->toBe(4);

    // Re-viewing calendar view returns same slots idempotently
    $secondResponse = $this->actingAs($this->operatorA)
        ->getJson(route('operator.courts.slots.index', [
            'court' => $this->courtA,
            'date' => '2026-11-12',
        ]));

    $secondResponse->assertOk()->assertJsonCount(4, 'data');
    expect(CourtSlot::where('court_id', $this->courtA->id)->where('date', '2026-11-12')->count())->toBe(4);
});

test('querying slots beyond venue booking horizon is rejected at policy boundary', function () {
    // Venue horizon is 14 days, requested date is 20 days ahead
    $response = $this->actingAs($this->operatorA)
        ->getJson(route('operator.courts.slots.index', [
            'court' => $this->courtA,
            'date' => '2026-11-30',
        ]));

    $response->assertForbidden()
        ->assertJsonPath('message', 'Date 2026-11-30 exceeds venue booking horizon of 14 days.');
});

test('querying slots for past dates is rejected at policy boundary', function () {
    $response = $this->actingAs($this->operatorA)
        ->getJson(route('operator.courts.slots.index', [
            'court' => $this->courtA,
            'date' => '2026-11-09',
        ]));

    $response->assertForbidden()
        ->assertJsonPath('message', 'Court slots cannot be queried or generated for past dates.');
});

test('operator of another venue cannot query court slots', function () {
    $response = $this->actingAs($this->operatorB)
        ->getJson(route('operator.courts.slots.index', [
            'court' => $this->courtA,
            'date' => '2026-11-12',
        ]));

    $response->assertForbidden();
});
