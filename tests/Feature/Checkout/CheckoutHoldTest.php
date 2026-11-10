<?php

declare(strict_types=1);

use App\Enums\PaymentStatus;
use App\Enums\SlotStatus;
use App\Enums\SportType;
use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Court;
use App\Models\CourtSlot;
use App\Models\User;
use App\Models\Venue;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    try {
        Redis::connection('cache')->flushdb();
    } catch (Throwable $e) {
    }
    Carbon::setTestNow('2026-11-10 10:00:00');

    $this->venue = Venue::create([
        'name' => 'Senayan Badminton Center',
        'slug' => 'senayan-badminton-center',
        'city' => 'Jakarta Pusat',
        'address' => 'Jl. Asia Afrika No. 1',
        'booking_horizon_days' => 30,
        'allow_reschedule' => true,
        'is_active' => true,
        'subscription_status' => SubscriptionStatus::Active,
    ]);

    $this->court = Court::create([
        'venue_id' => $this->venue->id,
        'name' => 'Court 1',
        'sport_type' => SportType::Badminton,
        'opening_time' => '07:00:00',
        'closing_time' => '23:00:00',
        'default_hourly_rate' => 120000,
        'is_active' => true,
    ]);

    $this->slot1 = CourtSlot::create([
        'court_id' => $this->court->id,
        'date' => '2026-11-12',
        'start_time' => '10:00:00',
        'end_time' => '11:00:00',
        'price' => 120000,
        'status' => SlotStatus::Available,
    ]);

    $this->slot2 = CourtSlot::create([
        'court_id' => $this->court->id,
        'date' => '2026-11-12',
        'start_time' => '11:00:00',
        'end_time' => '12:00:00',
        'price' => 120000,
        'status' => SlotStatus::Available,
    ]);

    $this->player = User::create([
        'name' => 'Rahmat Player',
        'email' => 'rahmat.player@example.com',
        'role' => UserRole::Player,
    ]);
});

test('unauthenticated request to hold slots is rejected with 401', function () {
    $response = $this->postJson('/checkout/hold', [
        'slot_ids' => [$this->slot1->id],
    ]);

    $response->assertStatus(401);
});

test('authenticated player can hold multiple slots and receive 201 with locked prices and 10-minute expiry', function () {
    $response = $this->actingAs($this->player)->postJson('/checkout/hold', [
        'court_id' => $this->court->id,
        'date' => '2026-11-12',
        'slot_ids' => [$this->slot2->id, $this->slot1->id],
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'status',
            'data' => [
                'booking_code',
                'total_amount',
                'payment_status',
                'expires_at',
                'items' => [
                    '*' => [
                        'court_slot_id',
                        'price',
                    ],
                ],
            ],
        ]);

    $data = $response->json('data');
    expect($data['total_amount'])->toBe(240000)
        ->and($data['payment_status'])->toBe('pending')
        ->and($data['items'])->toHaveCount(2);

    $this->slot1->refresh();
    $this->slot2->refresh();

    expect($this->slot1->status)->toBe(SlotStatus::Held)
        ->and($this->slot2->status)->toBe(SlotStatus::Held);
});

test('hold request on already locked or held slot returns HTTP 409 Conflict', function () {
    // Another process or player already holds slot1 in Redis
    $lock = Cache::lock("court_slot:lock:{$this->slot1->id}", 600);
    $lock->get();

    $response = $this->actingAs($this->player)->postJson('/checkout/hold', [
        'slot_ids' => [$this->slot1->id, $this->slot2->id],
    ]);

    $response->assertStatus(409)
        ->assertJson([
            'status' => 'error',
        ]);

    // Verify slot2 lock was rolled back
    $testLock2 = Cache::lock("court_slot:lock:{$this->slot2->id}", 600);
    expect($testLock2->get())->toBeTrue();
    $testLock2->release();
});

test('player can explicitly abandon checkout hold and release slots back to available', function () {
    $holdResponse = $this->actingAs($this->player)->postJson('/checkout/hold', [
        'slot_ids' => [$this->slot1->id],
    ]);

    $holdResponse->assertStatus(201);
    $bookingCode = $holdResponse->json('data.booking_code');

    $abandonResponse = $this->actingAs($this->player)->postJson("/checkout/{$bookingCode}/abandon");

    $abandonResponse->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'message' => 'Checkout hold has been abandoned.',
        ]);

    $this->slot1->refresh();
    expect($this->slot1->status)->toBe(SlotStatus::Available)
        ->and($this->slot1->locked_until)->toBeNull();

    $booking = Booking::where('booking_code', $bookingCode)->first();
    expect($booking->payment_status)->toBe(PaymentStatus::Expired);
});

test('player cannot abandon another players checkout hold', function () {
    $otherPlayer = User::create([
        'name' => 'Other Player',
        'email' => 'other@example.com',
        'role' => UserRole::Player,
    ]);

    $holdResponse = $this->actingAs($this->player)->postJson('/checkout/hold', [
        'slot_ids' => [$this->slot1->id],
    ]);
    $bookingCode = $holdResponse->json('data.booking_code');

    $response = $this->actingAs($otherPlayer)->postJson("/checkout/{$bookingCode}/abandon");
    $response->assertStatus(403);
});

test('slot availability endpoint reflects held slots as unbookable and expired held slots as bookable', function () {
    // Hold slot1
    $this->actingAs($this->player)->postJson('/checkout/hold', [
        'slot_ids' => [$this->slot1->id],
    ]);

    // Check slot availability
    $response = $this->getJson("/api/courts/{$this->court->id}/slots?date=2026-11-12");
    $response->assertStatus(200);

    $slots = collect($response->json('data'));
    $s1 = $slots->firstWhere('id', $this->slot1->id);
    $s2 = $slots->firstWhere('id', $this->slot2->id);

    expect($s1['status'])->toBe('held')
        ->and($s1['is_bookable'])->toBeFalse()
        ->and($s2['status'])->toBe('available')
        ->and($s2['is_bookable'])->toBeTrue();

    // Fast-forward past 10 minutes
    Carbon::setTestNow('2026-11-10 10:12:00');

    $response2 = $this->getJson("/api/courts/{$this->court->id}/slots?date=2026-11-12");
    $response2->assertStatus(200);

    $slotsAfter = collect($response2->json('data'));
    $s1After = $slotsAfter->firstWhere('id', $this->slot1->id);

    expect($s1After['status'])->toBe('available')
        ->and($s1After['is_bookable'])->toBeTrue();
});

test('checkout hold validates matching court_id and date against slot selection', function () {
    $otherCourt = Court::create([
        'venue_id' => $this->venue->id,
        'name' => 'Court 2',
        'sport_type' => SportType::Badminton,
        'opening_time' => '07:00:00',
        'closing_time' => '23:00:00',
        'default_hourly_rate' => 120000,
        'is_active' => true,
    ]);

    // Mismatched court_id
    $response = $this->actingAs($this->player)->postJson('/checkout/hold', [
        'court_id' => $otherCourt->id,
        'slot_ids' => [$this->slot1->id],
    ]);

    $response->assertStatus(409)
        ->assertJson([
            'status' => 'error',
            'message' => 'One or more selected slots do not match the specified court.',
        ]);

    // Mismatched date
    $response2 = $this->actingAs($this->player)->postJson('/checkout/hold', [
        'date' => '2026-11-25',
        'slot_ids' => [$this->slot1->id],
    ]);

    $response2->assertStatus(409)
        ->assertJson([
            'status' => 'error',
            'message' => 'One or more selected slots do not match the specified date.',
        ]);
});
