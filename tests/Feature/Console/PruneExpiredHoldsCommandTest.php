<?php

declare(strict_types=1);

use App\Enums\PaymentStatus;
use App\Enums\SlotStatus;
use App\Enums\SportType;
use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Models\Court;
use App\Models\CourtSlot;
use App\Models\User;
use App\Models\Venue;
use App\Services\BookingConcurrencyService;
use Carbon\Carbon;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
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

    $this->slot = CourtSlot::create([
        'court_id' => $this->court->id,
        'date' => '2026-11-12',
        'start_time' => '10:00:00',
        'end_time' => '11:00:00',
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

test('court-slots:prune-expired-holds command sweeps expired draft bookings and resets database slots', function () {
    // Hold slot at 10:00:00
    $booking = $this->service->holdSlots($this->player, [$this->slot->id]);
    expect($booking->payment_status)->toBe(PaymentStatus::Pending);

    // Fast-forward 15 minutes
    Carbon::setTestNow('2026-11-10 10:15:00');

    Artisan::call('court-slots:prune-expired-holds');

    $output = Artisan::output();
    expect($output)->toContain('1 expired checkout hold(s) pruned successfully');

    $booking->refresh();
    $this->slot->refresh();

    expect($booking->payment_status)->toBe(PaymentStatus::Expired)
        ->and($this->slot->status)->toBe(SlotStatus::Available)
        ->and($this->slot->locked_until)->toBeNull();
});

test('court-slots:prune-expired-holds command is scheduled to run every minute', function () {
    $schedule = app(Schedule::class);

    $events = collect($schedule->events());
    $matchingEvent = $events->first(function ($event) {
        return str_contains((string) $event->command, 'court-slots:prune-expired-holds');
    });

    expect($matchingEvent)->not->toBeNull()
        ->and($matchingEvent->expression)->toBe('* * * * *');
});
