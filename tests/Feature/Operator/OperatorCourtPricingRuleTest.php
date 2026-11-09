<?php

declare(strict_types=1);

use App\Enums\DayType;
use App\Enums\SlotStatus;
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
    Carbon::setTestNow('2026-11-10 10:00:00'); // Tuesday 10:00

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

    $this->courtA = Court::create([
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
});

afterEach(function () {
    Carbon::setTestNow();
});

test('operator can view list of pricing rules for their assigned venue court', function () {
    CourtPricingRule::create([
        'court_id' => $this->courtA->id,
        'day_type' => DayType::Weekday,
        'start_time' => '07:00:00',
        'end_time' => '17:00:00',
        'price_per_hour' => 120000,
        'tier_name' => 'Weekday Morning',
    ]);

    $response = $this->actingAs($this->operatorA)
        ->getJson(route('operator.courts.pricing-rules.index', $this->courtA));

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.tier_name', 'Weekday Morning')
        ->assertJsonPath('data.0.price_per_hour', 120000);
});

test('operator cannot view pricing rules of a court belonging to another venue', function () {
    $response = $this->actingAs($this->operatorB)
        ->getJson(route('operator.courts.pricing-rules.index', $this->courtA));

    $response->assertForbidden();
});

test('operator can create a pricing rule and future available slots are dynamically updated', function () {
    // Pre-create future available slot tomorrow at 18:00 (currently default 100k)
    $slot = CourtSlot::create([
        'court_id' => $this->courtA->id,
        'date' => '2026-11-11',
        'start_time' => '18:00:00',
        'end_time' => '19:00:00',
        'price' => 100000,
        'status' => SlotStatus::Available,
    ]);

    $response = $this->actingAs($this->operatorA)
        ->postJson(route('operator.courts.pricing-rules.store', $this->courtA), [
            'day_type' => 'weekday',
            'start_time' => '17:00',
            'end_time' => '23:00',
            'price_per_hour' => 175000,
            'tier_name' => 'Peak Night',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.tier_name', 'Peak Night')
        ->assertJsonPath('data.price_per_hour', 175000);

    $this->assertDatabaseHas('court_pricing_rules', [
        'court_id' => $this->courtA->id,
        'day_type' => 'weekday',
        'price_per_hour' => 175000,
    ]);

    // Slot price must be updated to 175000 via propagation
    expect($slot->fresh()->price)->toBe(175000);
});

test('operator pricing rule creation validates required fields and operating boundaries', function () {
    $response = $this->actingAs($this->operatorA)
        ->postJson(route('operator.courts.pricing-rules.store', $this->courtA), [
            'day_type' => 'invalid-day',
            'start_time' => '05:00', // Court opens at 07:00
            'end_time' => '06:00',
            'price_per_hour' => -50,
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['day_type', 'start_time', 'price_per_hour']);
});

test('operator can update pricing rule and future available slots are updated', function () {
    $rule = CourtPricingRule::create([
        'court_id' => $this->courtA->id,
        'day_type' => DayType::Weekday,
        'start_time' => '17:00:00',
        'end_time' => '23:00:00',
        'price_per_hour' => 175000,
        'tier_name' => 'Peak Night',
    ]);

    $slot = CourtSlot::create([
        'court_id' => $this->courtA->id,
        'date' => '2026-11-11',
        'start_time' => '18:00:00',
        'end_time' => '19:00:00',
        'price' => 175000,
        'status' => SlotStatus::Available,
    ]);

    $response = $this->actingAs($this->operatorA)
        ->putJson(route('operator.courts.pricing-rules.update', [$this->courtA, $rule]), [
            'price_per_hour' => 190000,
        ]);

    $response->assertOk()
        ->assertJsonPath('data.price_per_hour', 190000);

    expect($slot->fresh()->price)->toBe(190000);
});

test('operator can delete pricing rule and future available slots fallback to default rate', function () {
    $rule = CourtPricingRule::create([
        'court_id' => $this->courtA->id,
        'day_type' => DayType::Weekday,
        'start_time' => '17:00:00',
        'end_time' => '23:00:00',
        'price_per_hour' => 175000,
        'tier_name' => 'Peak Night',
    ]);

    $slot = CourtSlot::create([
        'court_id' => $this->courtA->id,
        'date' => '2026-11-11',
        'start_time' => '18:00:00',
        'end_time' => '19:00:00',
        'price' => 175000,
        'status' => SlotStatus::Available,
    ]);

    $response = $this->actingAs($this->operatorA)
        ->deleteJson(route('operator.courts.pricing-rules.destroy', [$this->courtA, $rule]));

    $response->assertNoContent();

    $this->assertDatabaseMissing('court_pricing_rules', ['id' => $rule->id]);

    // Slot price falls back to court default rate 100k
    expect($slot->fresh()->price)->toBe(100000);
});

test('operator of another venue cannot update or delete pricing rules', function () {
    $rule = CourtPricingRule::create([
        'court_id' => $this->courtA->id,
        'day_type' => DayType::Weekday,
        'start_time' => '17:00:00',
        'end_time' => '23:00:00',
        'price_per_hour' => 175000,
        'tier_name' => 'Peak Night',
    ]);

    $this->actingAs($this->operatorB)
        ->putJson(route('operator.courts.pricing-rules.update', [$this->courtA, $rule]), [
            'price_per_hour' => 200000,
        ])
        ->assertForbidden();

    $this->actingAs($this->operatorB)
        ->deleteJson(route('operator.courts.pricing-rules.destroy', [$this->courtA, $rule]))
        ->assertForbidden();
});
