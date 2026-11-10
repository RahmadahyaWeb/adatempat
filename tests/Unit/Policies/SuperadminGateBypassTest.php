<?php

declare(strict_types=1);

use App\Enums\PaymentStatus;
use App\Enums\SportType;
use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Court;
use App\Models\CourtSlot;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->superadmin = User::create([
        'name' => 'Global Superadmin',
        'email' => 'superadmin@adatempat.com',
        'role' => UserRole::Superadmin,
    ]);

    $this->venueA = Venue::create([
        'name' => 'Venue Alpha',
        'slug' => 'venue-alpha',
        'city' => 'Jakarta Selatan',
        'address' => 'Jl. Sudirman',
        'subscription_status' => SubscriptionStatus::Active,
    ]);

    $this->venueB = Venue::create([
        'name' => 'Venue Beta',
        'slug' => 'venue-beta',
        'city' => 'Surabaya',
        'address' => 'Jl. Basuki Rahmat',
        'subscription_status' => SubscriptionStatus::Active,
    ]);

    $this->operatorA = User::create([
        'name' => 'Operator Alpha',
        'email' => 'operator.a@adatempat.com',
        'role' => UserRole::VenueOperator,
        'venue_id' => $this->venueA->id,
    ]);

    $this->player1 = User::create([
        'name' => 'Player One',
        'email' => 'player.one@adatempat.com',
        'role' => UserRole::Player,
    ]);

    $this->player2 = User::create([
        'name' => 'Player Two',
        'email' => 'player.two@adatempat.com',
        'role' => UserRole::Player,
    ]);

    $this->courtB = Court::create([
        'venue_id' => $this->venueB->id,
        'name' => 'Court Beta 1',
        'sport_type' => SportType::Padel,
        'opening_time' => '08:00:00',
        'closing_time' => '22:00:00',
        'default_hourly_rate' => 150000,
        'is_active' => true,
    ]);

    $this->slotB = CourtSlot::create([
        'court_id' => $this->courtB->id,
        'date' => now()->addDays(2)->toDateString(),
        'start_time' => '10:00:00',
        'end_time' => '11:00:00',
        'price' => 150000,
        'status' => 'available',
    ]);

    $this->bookingPlayer2 = Booking::create([
        'booking_code' => 'ADT-20261110-9999',
        'user_id' => $this->player2->id,
        'venue_id' => $this->venueB->id,
        'total_amount' => 150000,
        'payment_status' => PaymentStatus::Paid,
        'qr_token' => 'qr-token-test-9999',
    ]);
});

test('gate before grants superadmin global authorization bypass across booking policies', function () {
    // Player 1 cannot view Player 2's booking
    expect(Gate::forUser($this->player1)->allows('view', $this->bookingPlayer2))->toBeFalse();

    // Operator A cannot view Venue B's booking
    expect(Gate::forUser($this->operatorA)->allows('view', $this->bookingPlayer2))->toBeFalse();

    // Superadmin bypasses policy via Gate::before
    expect(Gate::forUser($this->superadmin)->allows('view', $this->bookingPlayer2))->toBeTrue()
        ->and(Gate::forUser($this->superadmin)->allows('checkIn', $this->bookingPlayer2))->toBeTrue()
        ->and(Gate::forUser($this->superadmin)->allows('reschedule', $this->bookingPlayer2))->toBeTrue();
});

test('gate before grants superadmin global authorization bypass across court policies', function () {
    // Operator A cannot manage Venue B's court
    expect(Gate::forUser($this->operatorA)->allows('view', $this->courtB))->toBeFalse()
        ->and(Gate::forUser($this->operatorA)->allows('update', $this->courtB))->toBeFalse()
        ->and(Gate::forUser($this->operatorA)->allows('delete', $this->courtB))->toBeFalse();

    // Superadmin bypasses policy via Gate::before
    expect(Gate::forUser($this->superadmin)->allows('viewAny', Court::class))->toBeTrue()
        ->and(Gate::forUser($this->superadmin)->allows('view', $this->courtB))->toBeTrue()
        ->and(Gate::forUser($this->superadmin)->allows('create', Court::class))->toBeTrue()
        ->and(Gate::forUser($this->superadmin)->allows('update', $this->courtB))->toBeTrue()
        ->and(Gate::forUser($this->superadmin)->allows('delete', $this->courtB))->toBeTrue();
});

test('gate before grants superadmin global authorization bypass across court slot policies', function () {
    // Operator A cannot block/unblock Venue B's slot
    expect(Gate::forUser($this->operatorA)->allows('block', $this->slotB))->toBeFalse()
        ->and(Gate::forUser($this->operatorA)->allows('unblock', $this->slotB))->toBeFalse();

    // Player cannot block/unblock slots
    expect(Gate::forUser($this->player1)->allows('block', $this->slotB))->toBeFalse();

    // Superadmin bypasses policy via Gate::before
    expect(Gate::forUser($this->superadmin)->allows('block', $this->slotB))->toBeTrue()
        ->and(Gate::forUser($this->superadmin)->allows('unblock', $this->slotB))->toBeTrue();
});

test('gate before grants superadmin global authorization bypass across venue policies', function () {
    // Operator A cannot update Venue B
    expect(Gate::forUser($this->operatorA)->allows('update', $this->venueB))->toBeFalse();

    // Superadmin bypasses policy via Gate::before
    expect(Gate::forUser($this->superadmin)->allows('view', $this->venueB))->toBeTrue()
        ->and(Gate::forUser($this->superadmin)->allows('update', $this->venueB))->toBeTrue();
});
