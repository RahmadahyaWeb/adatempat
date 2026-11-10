<?php

declare(strict_types=1);

use App\Enums\SlotStatus;
use App\Enums\SportType;
use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Exceptions\SlotUnavailableException;
use App\Models\Court;
use App\Models\CourtSlot;
use App\Models\User;
use App\Models\Venue;
use App\Services\SlotBlockingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow('2026-11-10 10:00:00');

    $this->venue = Venue::create([
        'name' => 'Senayan Gelora',
        'slug' => 'senayan-gelora',
        'city' => 'Jakarta Pusat',
        'address' => 'Jl. Gelora Bung Karno No. 10',
        'is_active' => true,
        'subscription_status' => SubscriptionStatus::Active,
    ]);

    $this->operator = User::create([
        'name' => 'Receptionist Sarah',
        'email' => 'sarah@senayan.com',
        'role' => UserRole::VenueOperator,
        'venue_id' => $this->venue->id,
    ]);

    $this->court = Court::create([
        'venue_id' => $this->venue->id,
        'name' => 'Court 1',
        'sport_type' => SportType::Badminton,
        'opening_time' => '08:00:00',
        'closing_time' => '22:00:00',
        'default_hourly_rate' => 100000,
        'is_active' => true,
    ]);

    $this->otherVenue = Venue::create([
        'name' => 'Kemang Arena',
        'slug' => 'kemang-arena',
        'city' => 'Jakarta Selatan',
        'address' => 'Jl. Kemang Raya No. 5',
        'is_active' => true,
        'subscription_status' => SubscriptionStatus::Active,
    ]);

    $this->otherOperator = User::create([
        'name' => 'Receptionist Budi',
        'email' => 'budi@kemang.com',
        'role' => UserRole::VenueOperator,
        'venue_id' => $this->otherVenue->id,
    ]);

    $this->service = app(SlotBlockingService::class);
});

afterEach(function () {
    Carbon::setTestNow();
});

test('operator can transition an available slot to blocked with an explanatory reason', function () {
    $slot = CourtSlot::create([
        'court_id' => $this->court->id,
        'date' => '2026-11-10',
        'start_time' => '14:00:00',
        'end_time' => '15:00:00',
        'price' => 100000,
        'status' => SlotStatus::Available,
    ]);

    $blockedSlot = $this->service->blockSlot($this->operator, $slot, 'Walk-in Cash - Team Garuda');

    expect($blockedSlot->status)->toBe(SlotStatus::Blocked)
        ->and($blockedSlot->block_reason)->toBe('Walk-in Cash - Team Garuda');

    $this->assertDatabaseHas('court_slots', [
        'id' => $slot->id,
        'status' => SlotStatus::Blocked->value,
        'block_reason' => 'Walk-in Cash - Team Garuda',
    ]);
});

test('operator can transition an available slot to blocked without an explanatory reason', function () {
    $slot = CourtSlot::create([
        'court_id' => $this->court->id,
        'date' => '2026-11-10',
        'start_time' => '15:00:00',
        'end_time' => '16:00:00',
        'price' => 100000,
        'status' => SlotStatus::Available,
    ]);

    $blockedSlot = $this->service->blockSlot($this->operator, $slot);

    expect($blockedSlot->status)->toBe(SlotStatus::Blocked)
        ->and($blockedSlot->block_reason)->toBeNull();
});

test('blocking service asserts target slot belongs to operator assigned venue', function () {
    $slot = CourtSlot::create([
        'court_id' => $this->court->id,
        'date' => '2026-11-10',
        'start_time' => '16:00:00',
        'end_time' => '17:00:00',
        'price' => 100000,
        'status' => SlotStatus::Available,
    ]);

    try {
        $this->service->blockSlot($this->otherOperator, $slot, 'Unauthorized attempt');
        $this->fail('Expected HttpException 403 not thrown');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(403);
    }
});

test('attempting to block a slot currently held in checkout is rejected with HTTP 409 Conflict', function () {
    $slot = CourtSlot::create([
        'court_id' => $this->court->id,
        'date' => '2026-11-10',
        'start_time' => '17:00:00',
        'end_time' => '18:00:00',
        'price' => 100000,
        'status' => SlotStatus::Held,
        'locked_until' => Carbon::now()->addMinutes(8),
    ]);

    $this->expectException(SlotUnavailableException::class);
    $this->service->blockSlot($this->operator, $slot, 'Attempt to override held slot');
});

test('attempting to block a slot already booked is rejected with HTTP 409 Conflict', function () {
    $slot = CourtSlot::create([
        'court_id' => $this->court->id,
        'date' => '2026-11-10',
        'start_time' => '18:00:00',
        'end_time' => '19:00:00',
        'price' => 100000,
        'status' => SlotStatus::Booked,
    ]);

    $this->expectException(SlotUnavailableException::class);
    $this->service->blockSlot($this->operator, $slot, 'Attempt to override booked slot');
});

test('attempting to block a slot already blocked is rejected with HTTP 409 Conflict', function () {
    $slot = CourtSlot::create([
        'court_id' => $this->court->id,
        'date' => '2026-11-10',
        'start_time' => '19:00:00',
        'end_time' => '20:00:00',
        'price' => 100000,
        'status' => SlotStatus::Blocked,
        'block_reason' => 'Court Maintenance',
    ]);

    $this->expectException(SlotUnavailableException::class);
    $this->service->blockSlot($this->operator, $slot, 'Attempt to double block');
});

test('operator can transition a blocked slot back to available status and clear block reason', function () {
    $slot = CourtSlot::create([
        'court_id' => $this->court->id,
        'date' => '2026-11-10',
        'start_time' => '14:00:00',
        'end_time' => '15:00:00',
        'price' => 100000,
        'status' => SlotStatus::Blocked,
        'block_reason' => 'Emergency Court Maintenance',
    ]);

    $unblockedSlot = $this->service->unblockSlot($this->operator, $slot);

    expect($unblockedSlot->status)->toBe(SlotStatus::Available)
        ->and($unblockedSlot->block_reason)->toBeNull();

    $this->assertDatabaseHas('court_slots', [
        'id' => $slot->id,
        'status' => SlotStatus::Available->value,
        'block_reason' => null,
    ]);
});

test('unblocking service asserts target slot belongs to operator assigned venue', function () {
    $slot = CourtSlot::create([
        'court_id' => $this->court->id,
        'date' => '2026-11-10',
        'start_time' => '14:00:00',
        'end_time' => '15:00:00',
        'price' => 100000,
        'status' => SlotStatus::Blocked,
        'block_reason' => 'Maintenance',
    ]);

    try {
        $this->service->unblockSlot($this->otherOperator, $slot);
        $this->fail('Expected HttpException 403 not thrown');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(403);
    }
});

test('attempting to unblock a slot that is not in blocked status is rejected with HTTP 422', function () {
    $slot = CourtSlot::create([
        'court_id' => $this->court->id,
        'date' => '2026-11-10',
        'start_time' => '14:00:00',
        'end_time' => '15:00:00',
        'price' => 100000,
        'status' => SlotStatus::Available,
    ]);

    try {
        $this->service->unblockSlot($this->operator, $slot);
        $this->fail('Expected HttpException 422 not thrown');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(422)
            ->and($e->getMessage())->toContain('not currently in blocked status');
    }
});
