<?php

declare(strict_types=1);

use App\Enums\PaymentStatus;
use App\Enums\SlotStatus;
use App\Enums\SportType;
use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Exceptions\SlotUnavailableException;
use App\Models\Booking;
use App\Models\Court;
use App\Models\CourtSlot;
use App\Models\User;
use App\Models\Venue;
use App\Services\BookingConcurrencyService;
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

    $this->service = app(BookingConcurrencyService::class);
});

test('multi-slot lock acquisition sorts IDs numerically to prevent deadlocks and locks slots for 600s', function () {
    // Pass IDs in reverse order: [slot2->id, slot1->id]
    $booking = $this->service->holdSlots($this->player, [$this->slot2->id, $this->slot1->id]);

    expect($booking)->toBeInstanceOf(Booking::class)
        ->and($booking->user_id)->toBe($this->player->id)
        ->and($booking->venue_id)->toBe($this->venue->id)
        ->and($booking->total_amount)->toBe(240000)
        ->and($booking->payment_status)->toBe(PaymentStatus::Pending)
        ->and($booking->items)->toHaveCount(2);

    // Verify DB slots are held with locked_until set to 10 minutes in the future
    $this->slot1->refresh();
    $this->slot2->refresh();

    expect($this->slot1->status)->toBe(SlotStatus::Held)
        ->and($this->slot1->locked_until->toDateTimeString())->toBe('2026-11-10 10:10:00')
        ->and($this->slot2->status)->toBe(SlotStatus::Held)
        ->and($this->slot2->locked_until->toDateTimeString())->toBe('2026-11-10 10:10:00');

    // Verify Redis lock is held (cannot acquire another lock on slot1 or slot2)
    $lock1 = Cache::lock("court_slot:lock:{$this->slot1->id}", 600);
    expect($lock1->get())->toBeFalse();

    $lock2 = Cache::lock("court_slot:lock:{$this->slot2->id}", 600);
    expect($lock2->get())->toBeFalse();
});

test('fail-fast rollback releases partial locks immediately when lock contention occurs', function () {
    // Acquire a pre-existing lock on slot2
    $externalLock = Cache::lock("court_slot:lock:{$this->slot2->id}", 600);
    expect($externalLock->get())->toBeTrue();

    // Player attempts to hold [slot1, slot2]. Since slot2 is locked, slot1 lock must be rolled back!
    expect(function () {
        $this->service->holdSlots($this->player, [$this->slot1->id, $this->slot2->id]);
    })->toThrow(SlotUnavailableException::class);

    // slot1 must NOT remain locked in Redis!
    $testLock1 = Cache::lock("court_slot:lock:{$this->slot1->id}", 600);
    expect($testLock1->get())->toBeTrue();
    $testLock1->release();

    // DB status of slot1 must remain Available
    $this->slot1->refresh();
    expect($this->slot1->status)->toBe(SlotStatus::Available);
});

test('player can explicitly abandon checkout to release locks and reset slot status to available', function () {
    $booking = $this->service->holdSlots($this->player, [$this->slot1->id]);

    $this->slot1->refresh();
    expect($this->slot1->status)->toBe(SlotStatus::Held);

    // Player abandons checkout
    $this->service->abandonHold($booking);

    $booking->refresh();
    $this->slot1->refresh();

    expect($booking->payment_status)->toBe(PaymentStatus::Expired)
        ->and($this->slot1->status)->toBe(SlotStatus::Available)
        ->and($this->slot1->locked_until)->toBeNull();

    // Redis lock is released and can be acquired again
    $testLock = Cache::lock("court_slot:lock:{$this->slot1->id}", 600);
    expect($testLock->get())->toBeTrue();
    $testLock->release();
});

test('expired checkout hold is treated as available JIT and can be re-held by another player', function () {
    $player2 = User::create([
        'name' => 'Player Two',
        'email' => 'player2@example.com',
        'role' => UserRole::Player,
    ]);

    // Initial hold at 10:00:00
    $booking1 = $this->service->holdSlots($this->player, [$this->slot1->id]);

    // Fast-forward 11 minutes (hold expired)
    Carbon::setTestNow('2026-11-10 10:11:00');

    // Force release Redis lock to simulate Redis TTL expiration
    Cache::lock("court_slot:lock:{$this->slot1->id}")->forceRelease();

    // Player 2 can now hold slot1 JIT without waiting for background prune command
    $booking2 = $this->service->holdSlots($player2, [$this->slot1->id]);

    expect($booking2)->toBeInstanceOf(Booking::class)
        ->and($booking2->user_id)->toBe($player2->id)
        ->and($booking2->items)->toHaveCount(1);

    $this->slot1->refresh();
    expect($this->slot1->status)->toBe(SlotStatus::Held)
        ->and($this->slot1->locked_until->toDateTimeString())->toBe('2026-11-10 10:21:00');
});

test('scheduled prune cleans up expired draft bookings and resets database slot statuses', function () {
    $this->service->holdSlots($this->player, [$this->slot1->id, $this->slot2->id]);

    // Fast-forward past 10 minutes
    Carbon::setTestNow('2026-11-10 10:15:00');

    $prunedCount = $this->service->pruneExpiredHolds();

    expect($prunedCount)->toBe(1);

    $this->slot1->refresh();
    $this->slot2->refresh();

    expect($this->slot1->status)->toBe(SlotStatus::Available)
        ->and($this->slot1->locked_until)->toBeNull()
        ->and($this->slot2->status)->toBe(SlotStatus::Available)
        ->and($this->slot2->locked_until)->toBeNull();

    $booking = Booking::first();
    expect($booking->payment_status)->toBe(PaymentStatus::Expired);
});

test('concurrent contention between overlapping slot requests protects initial hold and rolls back contending locks', function () {
    $slot3 = CourtSlot::create([
        'court_id' => $this->court->id,
        'date' => '2026-11-12',
        'start_time' => '12:00:00',
        'end_time' => '13:00:00',
        'price' => 120000,
        'status' => SlotStatus::Available,
    ]);

    $player2 = User::create([
        'name' => 'Contending Player',
        'email' => 'contender@example.com',
        'role' => UserRole::Player,
    ]);

    // Player 1 successfully holds [slot1, slot2]
    $booking1 = $this->service->holdSlots($this->player, [$this->slot1->id, $this->slot2->id]);
    expect($booking1->items)->toHaveCount(2);

    // Player 2 attempts to hold [slot2, slot3] overlapping on slot2
    expect(function () use ($player2, $slot3) {
        $this->service->holdSlots($player2, [$this->slot2->id, $slot3->id]);
    })->toThrow(SlotUnavailableException::class);

    // Player 2's lock on slot3 must have rolled back
    $testLock3 = Cache::lock("court_slot:lock:{$slot3->id}", 600);
    expect($testLock3->get())->toBeTrue();
    $testLock3->release();

    // Player 1's locks on slot1 and slot2 remain intact and held
    $testLock1 = Cache::lock("court_slot:lock:{$this->slot1->id}", 600);
    expect($testLock1->get())->toBeFalse();

    $testLock2 = Cache::lock("court_slot:lock:{$this->slot2->id}", 600);
    expect($testLock2->get())->toBeFalse();
});

test('prune and abandon commands do not reset active holds when slot has been re-held JIT', function () {
    $player2 = User::create([
        'name' => 'Player Two',
        'email' => 'p2@example.com',
        'role' => UserRole::Player,
    ]);

    // Player 1 holds slot at 10:00:00 (expires at 10:10:00)
    $booking1 = $this->service->holdSlots($this->player, [$this->slot1->id]);

    // Fast-forward to 10:11:00 (Player 1 hold expired)
    Carbon::setTestNow('2026-11-10 10:11:00');
    Cache::lock("court_slot:lock:{$this->slot1->id}")->forceRelease();

    // Player 2 re-holds slot1 JIT at 10:11:00 (expires at 10:21:00)
    $booking2 = $this->service->holdSlots($player2, [$this->slot1->id]);

    // Player 1 tries to abandon expired booking
    $this->service->abandonHold($booking1);

    // slot1 must STILL be held by Player 2!
    $this->slot1->refresh();
    expect($this->slot1->status)->toBe(SlotStatus::Held)
        ->and($this->slot1->locked_until->toDateTimeString())->toBe('2026-11-10 10:21:00');

    // Prune sweeps expired holds
    $this->service->pruneExpiredHolds();

    // slot1 must STILL remain held by Player 2 (until 10:21:00)
    $this->slot1->refresh();
    expect($this->slot1->status)->toBe(SlotStatus::Held)
        ->and($this->slot1->locked_until->toDateTimeString())->toBe('2026-11-10 10:21:00');
});

test('hold request validates matching court_id and date when supplied', function () {
    $otherCourt = Court::create([
        'venue_id' => $this->venue->id,
        'name' => 'Court 2',
        'sport_type' => SportType::Badminton,
        'opening_time' => '07:00:00',
        'closing_time' => '23:00:00',
        'default_hourly_rate' => 120000,
        'is_active' => true,
    ]);

    // Mismatched court ID
    expect(function () use ($otherCourt) {
        $this->service->holdSlots($this->player, [$this->slot1->id], courtId: $otherCourt->id);
    })->toThrow(SlotUnavailableException::class, 'One or more selected slots do not match the specified court.');

    // Mismatched date
    expect(function () {
        $this->service->holdSlots($this->player, [$this->slot1->id], date: '2026-11-25');
    })->toThrow(SlotUnavailableException::class, 'One or more selected slots do not match the specified date.');
});
