<?php

declare(strict_types=1);

use App\Enums\DayType;
use App\Enums\SlotStatus;
use App\Enums\SportType;
use App\Enums\SubscriptionStatus;
use App\Models\Court;
use App\Models\CourtPricingRule;
use App\Models\CourtSlot;
use App\Models\Venue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->venue = Venue::create([
        'name' => 'Senayan Sports Center',
        'slug' => 'senayan-sports-center',
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
        'opening_time' => '06:00:00',
        'closing_time' => '23:00:00',
        'default_hourly_rate' => 120000,
        'is_active' => true,
    ]);
});

test('court pricing rules and court slots tables have expected columns and indexes', function () {
    expect(Schema::hasTable('court_pricing_rules'))->toBeTrue()
        ->and(Schema::hasTable('court_slots'))->toBeTrue();

    expect(Schema::hasColumns('court_pricing_rules', [
        'id', 'court_id', 'day_type', 'start_time', 'end_time', 'price_per_hour', 'tier_name', 'created_at', 'updated_at',
    ]))->toBeTrue();

    expect(Schema::hasColumns('court_slots', [
        'id', 'court_id', 'date', 'start_time', 'end_time', 'price', 'status', 'locked_until', 'block_reason', 'created_at', 'updated_at',
    ]))->toBeTrue();
});

test('composite unique constraint prevents duplicate slot creation for identical operational hours', function () {
    CourtSlot::create([
        'court_id' => $this->court->id,
        'date' => '2026-11-15',
        'start_time' => '08:00:00',
        'end_time' => '09:00:00',
        'price' => 120000,
        'status' => SlotStatus::Available,
    ]);

    // Attempting duplicate insertion on same court, date, and start_time must fail with QueryException
    expect(function () {
        CourtSlot::create([
            'court_id' => $this->court->id,
            'date' => '2026-11-15',
            'start_time' => '08:00:00',
            'end_time' => '09:00:00',
            'price' => 150000,
            'status' => SlotStatus::Available,
        ]);
    })->toThrow(QueryException::class);
});

test('court pricing rules cascade delete when court is deleted', function () {
    $rule = CourtPricingRule::create([
        'court_id' => $this->court->id,
        'day_type' => DayType::Weekday,
        'start_time' => '17:00:00',
        'end_time' => '23:00:00',
        'price_per_hour' => 150000,
        'tier_name' => 'Peak Evening',
    ]);

    $slot = CourtSlot::create([
        'court_id' => $this->court->id,
        'date' => '2026-11-15',
        'start_time' => '18:00:00',
        'end_time' => '19:00:00',
        'price' => 150000,
        'status' => SlotStatus::Available,
    ]);

    // Force delete court to verify cascade delete constraint in DB
    $this->court->forceDelete();

    expect(CourtPricingRule::find($rule->id))->toBeNull()
        ->and(CourtSlot::find($slot->id))->toBeNull();
});
