<?php

declare(strict_types=1);

use App\Enums\CheckInStatus;
use App\Enums\PaymentStatus;
use App\Enums\SlotStatus;
use App\Enums\SportType;
use App\Enums\UserRole;
use App\Exceptions\ReschedulePolicyException;
use App\Exceptions\SlotUnavailableException;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Court;
use App\Models\CourtSlot;
use App\Models\User;
use App\Models\Venue;
use App\Services\RescheduleService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

function createRescheduleTestEnvironment(
    bool $allowReschedule = true,
    int $slotDaysInFuture = 3,
    string $slotStartTime = '10:00:00',
    PaymentStatus $paymentStatus = PaymentStatus::Paid,
    CheckInStatus $checkInStatus = CheckInStatus::Unclaimed,
    ?Carbon $rescheduledAt = null,
    int $originalPrice = 100000,
    int $replacementPrice = 100000,
    SportType $courtSportType = SportType::Badminton,
    SportType $replacementCourtSportType = SportType::Badminton,
    bool $differentVenueForReplacement = false,
    SlotStatus $replacementSlotStatus = SlotStatus::Available,
): array {
    $slotDate = Carbon::now()->addDays($slotDaysInFuture)->toDateString();
    $replacementDate = Carbon::now()->addDays($slotDaysInFuture + 1)->toDateString();

    $venue = Venue::create([
        'name' => 'Senayan Gelora',
        'slug' => 'senayan-gelora',
        'city' => 'Jakarta Pusat',
        'address' => 'Jl. Gelora Bung Karno No. 10',
        'subscription_status' => 'active',
        'allow_reschedule' => $allowReschedule,
    ]);

    $court = Court::create([
        'venue_id' => $venue->id,
        'name' => 'Court 1',
        'sport_type' => $courtSportType,
        'default_hourly_rate' => $originalPrice,
        'is_active' => true,
    ]);

    $originalSlot = CourtSlot::create([
        'court_id' => $court->id,
        'date' => $slotDate,
        'start_time' => $slotStartTime,
        'end_time' => Carbon::parse($slotStartTime)->addHour()->format('H:i:s'),
        'price' => $originalPrice,
        'status' => SlotStatus::Booked,
    ]);

    $player = User::factory()->create([
        'name' => 'Player Rahmat',
        'role' => UserRole::Player,
    ]);

    $booking = Booking::create([
        'booking_code' => Booking::generateBookingCode(),
        'user_id' => $player->id,
        'venue_id' => $venue->id,
        'total_amount' => $originalPrice,
        'payment_status' => $paymentStatus,
        'check_in_status' => $checkInStatus,
        'qr_token' => Booking::generateQrToken(),
        'paid_at' => now(),
        'rescheduled_at' => $rescheduledAt,
    ]);

    $bookingItem = BookingItem::create([
        'booking_id' => $booking->id,
        'court_slot_id' => $originalSlot->id,
        'price_locked' => $originalPrice,
    ]);

    $targetVenue = $venue;
    if ($differentVenueForReplacement) {
        $targetVenue = Venue::create([
            'name' => 'Kuningan Sports Center',
            'slug' => 'kuningan-sports-center',
            'city' => 'Jakarta Selatan',
            'address' => 'Jl. HR Rasuna Said',
            'subscription_status' => 'active',
            'allow_reschedule' => true,
        ]);
    }

    $replacementCourt = ($targetVenue->id === $venue->id && $replacementCourtSportType === $courtSportType)
        ? $court
        : Court::create([
            'venue_id' => $targetVenue->id,
            'name' => 'Court 2',
            'sport_type' => $replacementCourtSportType,
            'default_hourly_rate' => $replacementPrice,
            'is_active' => true,
        ]);

    $replacementSlot = CourtSlot::create([
        'court_id' => $replacementCourt->id,
        'date' => $replacementDate,
        'start_time' => '14:00:00',
        'end_time' => '15:00:00',
        'price' => $replacementPrice,
        'status' => $replacementSlotStatus,
    ]);

    return compact('venue', 'court', 'originalSlot', 'player', 'booking', 'bookingItem', 'replacementCourt', 'replacementSlot');
}

test('successful reschedule executes atomic slot swap and records timestamp', function () {
    $env = createRescheduleTestEnvironment();
    $service = app(RescheduleService::class);

    $rescheduledBooking = $service->reschedule(
        player: $env['player'],
        booking: $env['booking'],
        replacementSlotId: $env['replacementSlot']->id,
    );

    // Verify booking state
    expect($rescheduledBooking->payment_status)->toBe(PaymentStatus::Rescheduled)
        ->and($rescheduledBooking->rescheduled_at)->not->toBeNull();

    // Verify booking item points to new slot
    $bookingItem = $rescheduledBooking->items()->first();
    expect($bookingItem->court_slot_id)->toBe($env['replacementSlot']->id);

    // Verify original slot is now available
    $originalSlot = $env['originalSlot']->fresh();
    expect($originalSlot->status)->toBe(SlotStatus::Available)
        ->and($originalSlot->locked_until)->toBeNull();

    // Verify replacement slot is now booked
    $replacementSlot = $env['replacementSlot']->fresh();
    expect($replacementSlot->status)->toBe(SlotStatus::Booked)
        ->and($replacementSlot->locked_until)->toBeNull();

    // Verify Redis lock was released post-commit
    $lockKey = "court_slot:lock:{$env['replacementSlot']->id}";
    expect(Cache::lock($lockKey, 10)->get())->toBeTrue();
});

test('gate 1: rejects reschedule when venue policy disables rescheduling', function () {
    $env = createRescheduleTestEnvironment(allowReschedule: false);
    $service = app(RescheduleService::class);

    expect(fn () => $service->reschedule(
        player: $env['player'],
        booking: $env['booking'],
        replacementSlotId: $env['replacementSlot']->id,
    ))->toThrow(ReschedulePolicyException::class, 'Venue policy does not permit rescheduling.');
});

test('gate 2: rejects reschedule when requested within 24 hours of slot start (H-1 cutoff)', function () {
    // Slot starts in 10 hours from now (< 24 hours)
    Carbon::setTestNow(Carbon::parse('2026-11-10 10:00:00'));

    $venue = Venue::create([
        'name' => 'Senayan Gelora',
        'slug' => 'senayan-gelora',
        'city' => 'Jakarta Pusat',
        'address' => 'Jl. Gelora Bung Karno No. 10',
        'subscription_status' => 'active',
        'allow_reschedule' => true,
    ]);

    $court = Court::create([
        'venue_id' => $venue->id,
        'name' => 'Court 1',
        'sport_type' => SportType::Badminton,
        'default_hourly_rate' => 100000,
        'is_active' => true,
    ]);

    // Starts at 2026-11-10 20:00:00 (10 hours away)
    $originalSlot = CourtSlot::create([
        'court_id' => $court->id,
        'date' => '2026-11-10',
        'start_time' => '20:00:00',
        'end_time' => '21:00:00',
        'price' => 100000,
        'status' => SlotStatus::Booked,
    ]);

    $player = User::factory()->create([
        'role' => UserRole::Player,
    ]);

    $booking = Booking::create([
        'booking_code' => Booking::generateBookingCode(),
        'user_id' => $player->id,
        'venue_id' => $venue->id,
        'total_amount' => 100000,
        'payment_status' => PaymentStatus::Paid,
        'check_in_status' => CheckInStatus::Unclaimed,
        'qr_token' => Booking::generateQrToken(),
        'paid_at' => now(),
    ]);

    BookingItem::create([
        'booking_id' => $booking->id,
        'court_slot_id' => $originalSlot->id,
        'price_locked' => 100000,
    ]);

    $replacementSlot = CourtSlot::create([
        'court_id' => $court->id,
        'date' => '2026-11-15',
        'start_time' => '10:00:00',
        'end_time' => '11:00:00',
        'price' => 100000,
        'status' => SlotStatus::Available,
    ]);

    $service = app(RescheduleService::class);

    expect(fn () => $service->reschedule(
        player: $player,
        booking: $booking,
        replacementSlotId: $replacementSlot->id,
    ))->toThrow(ReschedulePolicyException::class, 'Reschedule must be initiated at least 24 hours prior to the booked slot start time.');

    Carbon::setTestNow();
});

test('gate 3: rejects reschedule when booking is not in paid status', function () {
    $env = createRescheduleTestEnvironment(paymentStatus: PaymentStatus::Pending);
    $service = app(RescheduleService::class);

    expect(fn () => $service->reschedule(
        player: $env['player'],
        booking: $env['booking'],
        replacementSlotId: $env['replacementSlot']->id,
    ))->toThrow(ReschedulePolicyException::class, 'Only paid bookings can be rescheduled.');
});

test('gate 4: rejects reschedule when booking has already been checked in', function () {
    $env = createRescheduleTestEnvironment(checkInStatus: CheckInStatus::CheckedIn);
    $service = app(RescheduleService::class);

    expect(fn () => $service->reschedule(
        player: $env['player'],
        booking: $env['booking'],
        replacementSlotId: $env['replacementSlot']->id,
    ))->toThrow(ReschedulePolicyException::class, 'Cannot reschedule a booking that has already been checked in.');
});

test('gate 5: rejects subsequent reschedule attempts enforcing single-use limit', function () {
    $env = createRescheduleTestEnvironment(
        paymentStatus: PaymentStatus::Rescheduled,
        rescheduledAt: Carbon::now()->subDay(),
    );
    $service = app(RescheduleService::class);

    expect(fn () => $service->reschedule(
        player: $env['player'],
        booking: $env['booking'],
        replacementSlotId: $env['replacementSlot']->id,
    ))->toThrow(ReschedulePolicyException::class, 'This booking has already been rescheduled.');
});

test('gate 6: rejects replacement slot from different venue', function () {
    $env = createRescheduleTestEnvironment(differentVenueForReplacement: true);
    $service = app(RescheduleService::class);

    expect(fn () => $service->reschedule(
        player: $env['player'],
        booking: $env['booking'],
        replacementSlotId: $env['replacementSlot']->id,
    ))->toThrow(ReschedulePolicyException::class, 'Replacement slot must belong to the same venue.');
});

test('gate 7: rejects replacement slot on court with different sport type', function () {
    $env = createRescheduleTestEnvironment(
        courtSportType: SportType::Badminton,
        replacementCourtSportType: SportType::Tennis,
    );
    $service = app(RescheduleService::class);

    expect(fn () => $service->reschedule(
        player: $env['player'],
        booking: $env['booking'],
        replacementSlotId: $env['replacementSlot']->id,
    ))->toThrow(ReschedulePolicyException::class, 'Replacement slot must belong to an equivalent court with the same sport type.');
});

test('gate 8: rejects replacement slot when prices do not match exact parity', function () {
    $env = createRescheduleTestEnvironment(
        originalPrice: 100000,
        replacementPrice: 150000, // Higher price tier
    );
    $service = app(RescheduleService::class);

    expect(fn () => $service->reschedule(
        player: $env['player'],
        booking: $env['booking'],
        replacementSlotId: $env['replacementSlot']->id,
    ))->toThrow(ReschedulePolicyException::class, 'Replacement slot price must match the original booked price exactly.');
});

test('gate 9: rejects replacement slot that is not available', function () {
    $env = createRescheduleTestEnvironment(replacementSlotStatus: SlotStatus::Booked);
    $service = app(RescheduleService::class);

    expect(fn () => $service->reschedule(
        player: $env['player'],
        booking: $env['booking'],
        replacementSlotId: $env['replacementSlot']->id,
    ))->toThrow(SlotUnavailableException::class);
});

test('gate 10: rejects replacement slot scheduled in the past', function () {
    $env = createRescheduleTestEnvironment();
    $pastSlot = CourtSlot::create([
        'court_id' => $env['court']->id,
        'date' => Carbon::yesterday()->toDateString(),
        'start_time' => '10:00:00',
        'end_time' => '11:00:00',
        'price' => 100000,
        'status' => SlotStatus::Available,
    ]);

    $service = app(RescheduleService::class);

    expect(fn () => $service->reschedule(
        player: $env['player'],
        booking: $env['booking'],
        replacementSlotId: $pastSlot->id,
    ))->toThrow(ReschedulePolicyException::class, 'Replacement slot must be scheduled in the future.');
});

test('gate 11: rejects reschedule when player does not own the booking (defense-in-depth)', function () {
    $env = createRescheduleTestEnvironment();
    $otherPlayer = User::factory()->create(['role' => UserRole::Player]);

    $service = app(RescheduleService::class);

    expect(fn () => $service->reschedule(
        player: $otherPlayer,
        booking: $env['booking'],
        replacementSlotId: $env['replacementSlot']->id,
    ))->toThrow(ReschedulePolicyException::class, 'Player does not own this booking.');
});

test('concurrent lock on replacement slot fails fast and leaves state intact', function () {
    $env = createRescheduleTestEnvironment();
    $service = app(RescheduleService::class);

    // Simulate another player holding the Redis lock on the replacement slot
    $lockKey = "court_slot:lock:{$env['replacementSlot']->id}";
    $externalLock = Cache::lock($lockKey, 30, 'concurrent_holder');
    expect($externalLock->get())->toBeTrue();

    try {
        expect(fn () => $service->reschedule(
            player: $env['player'],
            booking: $env['booking'],
            replacementSlotId: $env['replacementSlot']->id,
        ))->toThrow(SlotUnavailableException::class);

        // Verify state is untouched
        expect($env['originalSlot']->fresh()->status)->toBe(SlotStatus::Booked)
            ->and($env['replacementSlot']->fresh()->status)->toBe(SlotStatus::Available)
            ->and($env['booking']->fresh()->payment_status)->toBe(PaymentStatus::Paid)
            ->and($env['booking']->fresh()->rescheduled_at)->toBeNull();
    } finally {
        $externalLock->forceRelease();
    }
});

test('atomic rollback on database failure restores original states and releases lock', function () {
    $env = createRescheduleTestEnvironment();
    $service = app(RescheduleService::class);

    // Mock Booking to throw exception during update in transaction
    Booking::saving(function ($model) {
        if ($model->payment_status === PaymentStatus::Rescheduled) {
            throw new RuntimeException('Database forced failure during reschedule commit.');
        }
    });

    try {
        expect(fn () => $service->reschedule(
            player: $env['player'],
            booking: $env['booking'],
            replacementSlotId: $env['replacementSlot']->id,
        ))->toThrow(RuntimeException::class, 'Database forced failure during reschedule commit.');

        // Original slot must remain Booked
        expect($env['originalSlot']->fresh()->status)->toBe(SlotStatus::Booked);

        // Replacement slot must remain Available
        expect($env['replacementSlot']->fresh()->status)->toBe(SlotStatus::Available);

        // Booking must remain Paid and not rescheduled
        expect($env['booking']->fresh()->payment_status)->toBe(PaymentStatus::Paid)
            ->and($env['booking']->fresh()->rescheduled_at)->toBeNull();

        // Lock must be released
        $lockKey = "court_slot:lock:{$env['replacementSlot']->id}";
        expect(Cache::lock($lockKey, 10)->get())->toBeTrue();
    } finally {
        // Clear the model hook
        Booking::flushEventListeners();
    }
});
