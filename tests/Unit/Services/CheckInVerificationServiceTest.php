<?php

declare(strict_types=1);

use App\Enums\CheckInStatus;
use App\Enums\PaymentStatus;
use App\Enums\SlotStatus;
use App\Enums\SportType;
use App\Enums\UserRole;
use App\Exceptions\DuplicateCheckInException;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Court;
use App\Models\CourtSlot;
use App\Models\User;
use App\Models\Venue;
use App\Services\CheckInVerificationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

function createCheckInTestEnvironment(
    string $slotDate = 'today',
    PaymentStatus $paymentStatus = PaymentStatus::Paid,
    CheckInStatus $checkInStatus = CheckInStatus::Unclaimed,
): array {
    $date = $slotDate === 'today' ? Carbon::today()->toDateString() : $slotDate;

    $venue = Venue::create([
        'name' => 'Senayan Gelora',
        'slug' => 'senayan-gelora',
        'city' => 'Jakarta Pusat',
        'address' => 'Jl. Gelora Bung Karno No. 10',
        'subscription_status' => 'active',
    ]);

    $operator = User::factory()->create([
        'name' => 'Receptionist Sarah',
        'email' => 'sarah@senayan.com',
        'role' => UserRole::VenueOperator,
        'venue_id' => $venue->id,
    ]);

    $court = Court::create([
        'venue_id' => $venue->id,
        'name' => 'Court 1',
        'sport_type' => SportType::Badminton,
        'default_hourly_rate' => 100000,
        'is_active' => true,
    ]);

    $slot = CourtSlot::create([
        'court_id' => $court->id,
        'date' => $date,
        'start_time' => '14:00:00',
        'end_time' => '15:00:00',
        'price' => 100000,
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
        'total_amount' => 100000,
        'payment_status' => $paymentStatus,
        'check_in_status' => $checkInStatus,
        'qr_token' => Booking::generateQrToken(),
        'paid_at' => now(),
    ]);

    BookingItem::create([
        'booking_id' => $booking->id,
        'court_slot_id' => $slot->id,
        'price_locked' => 100000,
    ]);

    return compact('venue', 'operator', 'court', 'slot', 'player', 'booking');
}

test('successful check-in via QR token updates status, timestamp, and operator ID', function () {
    $env = createCheckInTestEnvironment();
    $service = app(CheckInVerificationService::class);

    $checkedInBooking = $service->verifyAndCheckIn(
        operator: $env['operator'],
        qrToken: $env['booking']->qr_token,
    );

    expect($checkedInBooking->check_in_status)->toBe(CheckInStatus::CheckedIn)
        ->and($checkedInBooking->checked_in_by)->toBe($env['operator']->id)
        ->and($checkedInBooking->checked_in_at)->not->toBeNull();
});

test('successful check-in via booking code updates status, timestamp, and operator ID', function () {
    $env = createCheckInTestEnvironment();
    $service = app(CheckInVerificationService::class);

    $checkedInBooking = $service->verifyAndCheckIn(
        operator: $env['operator'],
        bookingCode: $env['booking']->booking_code,
    );

    expect($checkedInBooking->check_in_status)->toBe(CheckInStatus::CheckedIn)
        ->and($checkedInBooking->checked_in_by)->toBe($env['operator']->id)
        ->and($checkedInBooking->checked_in_at)->not->toBeNull();
});

test('gate 1: rejects cross-venue ticket with 403 Forbidden', function () {
    $env = createCheckInTestEnvironment();
    $otherVenue = Venue::create([
        'name' => 'Other Arena',
        'slug' => 'other-arena',
        'city' => 'Bandung',
        'address' => 'Jl. Riau No. 5',
        'subscription_status' => 'active',
    ]);
    $otherOperator = User::factory()->create([
        'name' => 'Operator Other',
        'role' => UserRole::VenueOperator,
        'venue_id' => $otherVenue->id,
    ]);

    $service = app(CheckInVerificationService::class);

    expect(fn () => $service->verifyAndCheckIn(
        operator: $otherOperator,
        qrToken: $env['booking']->qr_token,
    ))->toThrow(HttpException::class, 'This ticket belongs to another venue.');
});

test('gate 2: rejects check-in for unpaid booking with 422 Unprocessable Entity', function () {
    $env = createCheckInTestEnvironment(paymentStatus: PaymentStatus::Pending);
    $service = app(CheckInVerificationService::class);

    expect(fn () => $service->verifyAndCheckIn(
        operator: $env['operator'],
        qrToken: $env['booking']->qr_token,
    ))->toThrow(HttpException::class, 'Only paid bookings can be checked in.');
});

test('gate 3: rejects check-in for slot date in future or past with 422 Unprocessable Entity', function () {
    $tomorrow = Carbon::tomorrow()->toDateString();
    $env = createCheckInTestEnvironment(slotDate: $tomorrow);
    $service = app(CheckInVerificationService::class);

    expect(fn () => $service->verifyAndCheckIn(
        operator: $env['operator'],
        qrToken: $env['booking']->qr_token,
    ))->toThrow(HttpException::class, 'Check-in is only permitted on the scheduled operational date.');
});

test('gate 4: rejects non-existent or invalid QR token with 404', function () {
    $env = createCheckInTestEnvironment();
    $service = app(CheckInVerificationService::class);

    expect(fn () => $service->verifyAndCheckIn(
        operator: $env['operator'],
        qrToken: 'invalid-non-existent-qr-token-string',
    ))->toThrow(HttpException::class, 'Booking not found.');
});

test('gate 4: rejects mismatch between qr token and booking code with 422', function () {
    $env = createCheckInTestEnvironment();
    $service = app(CheckInVerificationService::class);

    expect(fn () => $service->verifyAndCheckIn(
        operator: $env['operator'],
        qrToken: $env['booking']->qr_token,
        bookingCode: 'ADT-DIFFERENT-CODE',
    ))->toThrow(HttpException::class, 'Verification credentials mismatch.');
});

test('duplicate check-in throws DuplicateCheckInException with original audit details', function () {
    $env = createCheckInTestEnvironment();
    $originalOperator = $env['operator'];
    $booking = $env['booking'];
    $checkedInTime = Carbon::now()->subMinutes(15);

    $booking->update([
        'check_in_status' => CheckInStatus::CheckedIn,
        'checked_in_at' => $checkedInTime,
        'checked_in_by' => $originalOperator->id,
    ]);

    $service = app(CheckInVerificationService::class);

    try {
        $service->verifyAndCheckIn(
            operator: $originalOperator,
            qrToken: $booking->qr_token,
        );
        $this->fail('Expected DuplicateCheckInException was not thrown.');
    } catch (DuplicateCheckInException $e) {
        expect($e->getStatusCode())->toBe(422)
            ->and($e->getMessage())->toContain('already been checked in')
            ->and($e->staffName)->toBe($originalOperator->name)
            ->and($e->checkedInBy)->toBe($originalOperator->id)
            ->and($e->checkedInAt)->not->toBeNull();
    }
});
