<?php

declare(strict_types=1);

use App\Enums\CheckInStatus;
use App\Enums\PaymentStatus;
use App\Enums\SlotStatus;
use App\Enums\SportType;
use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Exceptions\BookingHorizonExceededException;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Court;
use App\Models\CourtSlot;
use App\Models\User;
use App\Models\Venue;
use App\Services\OperatorTimelineService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow('2026-11-10 10:00:00');

    $this->venue = Venue::create([
        'name' => 'Senayan Gelora',
        'slug' => 'senayan-gelora',
        'city' => 'Jakarta Pusat',
        'address' => 'Jl. Gelora Bung Karno No. 10',
        'booking_horizon_days' => 14,
        'is_active' => true,
        'subscription_status' => SubscriptionStatus::Active,
    ]);

    $this->operator = User::create([
        'name' => 'Receptionist Sarah',
        'email' => 'sarah@senayan.com',
        'role' => UserRole::VenueOperator,
        'venue_id' => $this->venue->id,
    ]);

    $this->player = User::create([
        'name' => 'Budi Santoso',
        'email' => 'budi@example.com',
        'role' => UserRole::Player,
    ]);

    $this->court1 = Court::create([
        'venue_id' => $this->venue->id,
        'name' => 'Badminton Court 1',
        'sport_type' => SportType::Badminton,
        'opening_time' => '08:00:00',
        'closing_time' => '12:00:00',
        'default_hourly_rate' => 100000,
        'is_active' => true,
    ]);

    $this->court2 = Court::create([
        'venue_id' => $this->venue->id,
        'name' => 'Badminton Court 2',
        'sport_type' => SportType::Badminton,
        'opening_time' => '08:00:00',
        'closing_time' => '10:00:00',
        'default_hourly_rate' => 120000,
        'is_active' => true,
    ]);

    $this->otherVenue = Venue::create([
        'name' => 'Kemang Arena',
        'slug' => 'kemang-arena',
        'city' => 'Jakarta Selatan',
        'address' => 'Jl. Kemang Raya No. 5',
        'booking_horizon_days' => 7,
        'is_active' => true,
        'subscription_status' => SubscriptionStatus::Active,
    ]);

    $this->otherOperator = User::create([
        'name' => 'Receptionist Andi',
        'email' => 'andi@kemang.com',
        'role' => UserRole::VenueOperator,
        'venue_id' => $this->otherVenue->id,
    ]);

    $this->service = app(OperatorTimelineService::class);
});

afterEach(function () {
    Carbon::setTestNow();
});

test('timeline service generates missing slots JIT and formats daily court schedule', function () {
    $timeline = $this->service->getTimeline($this->operator, '2026-11-10');

    expect($timeline['date'])->toBe('2026-11-10')
        ->and($timeline['venue']['id'])->toBe($this->venue->id)
        ->and($timeline['venue']['name'])->toBe('Senayan Gelora')
        ->and($timeline['courts'])->toHaveCount(2);

    // Court 1 has 4 one-hour blocks: 08-09, 09-10, 10-11, 11-12
    $c1 = $timeline['courts'][0];
    expect($c1['id'])->toBe($this->court1->id)
        ->and($c1['name'])->toBe('Badminton Court 1')
        ->and($c1['slots'])->toHaveCount(4);

    expect($c1['slots'][0]['start_time'])->toBe('08:00:00')
        ->and($c1['slots'][0]['end_time'])->toBe('09:00:00')
        ->and($c1['slots'][0]['status'])->toBe(SlotStatus::Available->value)
        ->and($c1['slots'][0]['booking'])->toBeNull();
});

test('timeline returns reservation details for booked slots including customer identifiers, payment status, and check-in timestamp', function () {
    // Generate slots
    $this->service->getTimeline($this->operator, '2026-11-10');

    $slot = CourtSlot::where('court_id', $this->court1->id)
        ->where('date', '2026-11-10')
        ->where('start_time', '09:00:00')
        ->firstOrFail();

    $slot->update([
        'status' => SlotStatus::Booked,
    ]);

    $booking = Booking::create([
        'booking_code' => 'ADT-20261110-WXYZ',
        'user_id' => $this->player->id,
        'venue_id' => $this->venue->id,
        'total_amount' => 100000,
        'payment_status' => PaymentStatus::Paid,
        'check_in_status' => CheckInStatus::CheckedIn,
        'qr_token' => Booking::generateQrToken(),
        'checked_in_at' => Carbon::parse('2026-11-10 09:05:00'),
        'checked_in_by' => $this->operator->id,
    ]);

    BookingItem::create([
        'booking_id' => $booking->id,
        'court_slot_id' => $slot->id,
        'price_locked' => 100000,
    ]);

    $timeline = $this->service->getTimeline($this->operator, '2026-11-10');
    $court1Data = collect($timeline['courts'])->firstWhere('id', $this->court1->id);
    $slotData = collect($court1Data['slots'])->firstWhere('id', $slot->id);

    expect($slotData['status'])->toBe(SlotStatus::Booked->value)
        ->and($slotData['booking'])->not->toBeNull()
        ->and($slotData['booking']['booking_code'])->toBe('ADT-20261110-WXYZ')
        ->and($slotData['booking']['customer_id'])->toBe($this->player->id)
        ->and($slotData['booking']['customer_name'])->toBe('Budi Santoso')
        ->and($slotData['booking']['payment_status'])->toBe(PaymentStatus::Paid->value)
        ->and($slotData['booking']['check_in_status'])->toBe(CheckInStatus::CheckedIn->value)
        ->and($slotData['booking']['checked_in_at'])->toBe(Carbon::parse('2026-11-10 09:05:00')->toISOString());
});

test('timeline includes block reason for blocked slots', function () {
    // Generate slots
    $this->service->getTimeline($this->operator, '2026-11-10');

    $slot = CourtSlot::where('court_id', $this->court1->id)
        ->where('date', '2026-11-10')
        ->where('start_time', '10:00:00')
        ->firstOrFail();

    $slot->update([
        'status' => SlotStatus::Blocked,
        'block_reason' => 'Walk-in Cash - Team Garuda',
    ]);

    $timeline = $this->service->getTimeline($this->operator, '2026-11-10');
    $court1Data = collect($timeline['courts'])->firstWhere('id', $this->court1->id);
    $slotData = collect($court1Data['slots'])->firstWhere('id', $slot->id);

    expect($slotData['status'])->toBe(SlotStatus::Blocked->value)
        ->and($slotData['block_reason'])->toBe('Walk-in Cash - Team Garuda')
        ->and($slotData['booking'])->toBeNull();
});

test('timeline filters by court_id when optional court_id parameter is supplied', function () {
    $timeline = $this->service->getTimeline($this->operator, '2026-11-10', $this->court2->id);

    expect($timeline['courts'])->toHaveCount(1)
        ->and($timeline['courts'][0]['id'])->toBe($this->court2->id);
});

test('timeline rejects querying past date or date beyond booking horizon', function () {
    $this->expectException(BookingHorizonExceededException::class);
    $this->service->getTimeline($this->operator, '2026-11-09');
});
