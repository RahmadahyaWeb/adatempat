<?php

declare(strict_types=1);

use App\Enums\SlotStatus;
use App\Enums\SportType;
use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Models\Court;
use App\Models\CourtSlot;
use App\Models\User;
use App\Models\Venue;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow('2026-11-10 10:00:00');

    $this->venueA = Venue::create([
        'name' => 'Venue Alpha',
        'slug' => 'venue-alpha',
        'city' => 'Jakarta Selatan',
        'address' => 'Jl. Radio Dalam No. 1',
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
        'closing_time' => '22:00:00',
        'default_hourly_rate' => 100000,
        'is_active' => true,
    ]);

    $this->slotA = CourtSlot::create([
        'court_id' => $this->courtA->id,
        'date' => '2026-11-10',
        'start_time' => '14:00:00',
        'end_time' => '15:00:00',
        'price' => 100000,
        'status' => SlotStatus::Available,
    ]);

    $this->venueB = Venue::create([
        'name' => 'Venue Beta',
        'slug' => 'venue-beta',
        'city' => 'Jakarta Barat',
        'address' => 'Jl. Panjang No. 2',
        'is_active' => true,
        'subscription_status' => SubscriptionStatus::Active,
    ]);

    $this->operatorB = User::create([
        'name' => 'Operator Beta',
        'email' => 'beta@venue.com',
        'role' => UserRole::VenueOperator,
        'venue_id' => $this->venueB->id,
    ]);

    $this->player = User::create([
        'name' => 'Player Rahmat',
        'email' => 'rahmat@example.com',
        'role' => UserRole::Player,
    ]);
});

afterEach(function () {
    Carbon::setTestNow();
});

test('operator can successfully block an available slot with an explanatory reason', function () {
    $response = $this->actingAs($this->operatorA)
        ->postJson(route('operator.slots.block', $this->slotA), [
            'reason' => 'Walk-in Cash - Team Garuda',
        ]);

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.status', SlotStatus::Blocked->value)
        ->assertJsonPath('data.block_reason', 'Walk-in Cash - Team Garuda');

    $this->assertDatabaseHas('court_slots', [
        'id' => $this->slotA->id,
        'status' => SlotStatus::Blocked->value,
        'block_reason' => 'Walk-in Cash - Team Garuda',
    ]);
});

test('operator can block an available slot without specifying a reason', function () {
    $response = $this->actingAs($this->operatorA)
        ->postJson(route('operator.slots.block', $this->slotA));

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.status', SlotStatus::Blocked->value)
        ->assertJsonPath('data.block_reason', null);
});

test('attempting to block a slot currently held in checkout returns HTTP 409 Conflict', function () {
    $this->slotA->update([
        'status' => SlotStatus::Held,
        'locked_until' => Carbon::now()->addMinutes(8),
    ]);

    $response = $this->actingAs($this->operatorA)
        ->postJson(route('operator.slots.block', $this->slotA), [
            'reason' => 'Walk-in attempt',
        ]);

    $response->assertStatus(409)
        ->assertJsonPath('status', 'error')
        ->assertJsonPath('message', "Slot {$this->slotA->id} is currently held in checkout and cannot be blocked.");
});

test('attempting to block an already booked slot returns HTTP 409 Conflict', function () {
    $this->slotA->update([
        'status' => SlotStatus::Booked,
    ]);

    $response = $this->actingAs($this->operatorA)
        ->postJson(route('operator.slots.block', $this->slotA), [
            'reason' => 'Walk-in override',
        ]);

    $response->assertStatus(409)
        ->assertJsonPath('status', 'error')
        ->assertJsonPath('message', "Slot {$this->slotA->id} is already booked and cannot be blocked.");
});

test('operator can successfully unblock a previously blocked maintenance slot', function () {
    $this->slotA->update([
        'status' => SlotStatus::Blocked,
        'block_reason' => 'Emergency net repair',
    ]);

    $response = $this->actingAs($this->operatorA)
        ->postJson(route('operator.slots.unblock', $this->slotA));

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.status', SlotStatus::Available->value)
        ->assertJsonPath('data.block_reason', null);

    $this->assertDatabaseHas('court_slots', [
        'id' => $this->slotA->id,
        'status' => SlotStatus::Available->value,
        'block_reason' => null,
    ]);
});

test('attempting to unblock a slot that is not in blocked status returns HTTP 422', function () {
    $response = $this->actingAs($this->operatorA)
        ->postJson(route('operator.slots.unblock', $this->slotA));

    $response->assertStatus(422)
        ->assertJsonPath('message', "Slot {$this->slotA->id} is not currently in blocked status.");
});

test('operator of another venue cannot block or unblock slot (HTTP 403 Forbidden)', function () {
    $responseBlock = $this->actingAs($this->operatorB)
        ->postJson(route('operator.slots.block', $this->slotA), [
            'reason' => 'Cross-tenant blocking',
        ]);

    $responseBlock->assertForbidden();

    $this->slotA->update(['status' => SlotStatus::Blocked]);

    $responseUnblock = $this->actingAs($this->operatorB)
        ->postJson(route('operator.slots.unblock', $this->slotA));

    $responseUnblock->assertForbidden();
});

test('unauthenticated user cannot block or unblock court slots (HTTP 401)', function () {
    $responseBlock = $this->postJson(route('operator.slots.block', $this->slotA));
    $responseBlock->assertUnauthorized();

    $responseUnblock = $this->postJson(route('operator.slots.unblock', $this->slotA));
    $responseUnblock->assertUnauthorized();
});

test('player role cannot block or unblock court slots (HTTP 403 Forbidden)', function () {
    $responseBlock = $this->actingAs($this->player)
        ->postJson(route('operator.slots.block', $this->slotA));
    $responseBlock->assertForbidden();

    $responseUnblock = $this->actingAs($this->player)
        ->postJson(route('operator.slots.unblock', $this->slotA));
    $responseUnblock->assertForbidden();
});
