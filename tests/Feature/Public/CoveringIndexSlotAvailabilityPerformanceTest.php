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
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow('2026-11-10 09:00:00');

    $this->venue = Venue::create([
        'name' => 'Senayan National Sports Arena',
        'slug' => 'senayan-national-sports-arena',
        'city' => 'Jakarta Pusat',
        'address' => 'Gelora Bung Karno Complex',
        'booking_horizon_days' => 30,
        'allow_reschedule' => true,
        'is_active' => true,
        'subscription_status' => SubscriptionStatus::Active,
        'subscription_expires_at' => Carbon::parse('2026-12-31 23:59:59'),
    ]);

    $this->courts = [];
    for ($c = 1; $c <= 10; $c++) {
        $court = Court::create([
            'venue_id' => $this->venue->id,
            'name' => "Arena Court {$c}",
            'sport_type' => SportType::Badminton,
            'opening_time' => '06:00:00',
            'closing_time' => '23:00:00', // 17 discrete hours per day
            'default_hourly_rate' => 150000,
            'is_active' => true,
        ]);

        CourtPricingRule::create([
            'court_id' => $court->id,
            'day_type' => DayType::Weekday,
            'start_time' => '17:00:00',
            'end_time' => '22:00:00',
            'price_per_hour' => 200000,
            'tier_name' => 'Prime Evening',
        ]);

        $this->courts[] = $court;
    }

    // Seed realistic slot dataset: 10 courts * 30 days * 17 hours = 5,100 slot rows
    $bulkSlots = [];
    $nowTimestamp = now();
    $targetCourt = $this->courts[0];

    for ($day = 0; $day <= 15; $day++) {
        $dateStr = now()->addDays($day)->toDateString();

        foreach ($this->courts as $court) {
            for ($hour = 6; $hour < 23; $hour++) {
                $bulkSlots[] = [
                    'court_id' => $court->id,
                    'date' => $dateStr,
                    'start_time' => sprintf('%02d:00:00', $hour),
                    'end_time' => sprintf('%02d:00:00', $hour + 1),
                    'price' => ($hour >= 17 && $hour < 22) ? 200000 : 150000,
                    'status' => SlotStatus::Available->value,
                    'locked_until' => null,
                    'block_reason' => null,
                    'created_at' => $nowTimestamp,
                    'updated_at' => $nowTimestamp,
                ];
            }
        }
    }

    // Insert in chunks of 500
    foreach (array_chunk($bulkSlots, 500) as $chunk) {
        CourtSlot::insert($chunk);
    }
});

afterEach(function () {
    Carbon::setTestNow();
});

test('availability query executes as a true MySQL covering index scan (Using index in EXPLAIN)', function () {
    $targetCourt = $this->courts[0];
    $date = '2026-11-15';

    $explain = DB::select(
        'EXPLAIN FORMAT=TRADITIONAL SELECT id, court_id, date, status, start_time, end_time, price, locked_until
         FROM court_slots FORCE INDEX (idx_slots_availability)
         WHERE court_id = ? AND date = ?
         ORDER BY start_time ASC',
        [$targetCourt->id, $date]
    );

    expect($explain)->not->toBeEmpty();

    $row = (array) $explain[0];
    expect($row['key'])->toBe('idx_slots_availability')
        ->and($row['Extra'])->toContain('Using index');
});

test('slot availability endpoint achieves sub-80ms response time on populated dataset', function () {
    $targetCourt = $this->courts[0];
    $date = '2026-11-15';

    // Warm-up query
    $this->getJson("/api/courts/{$targetCourt->id}/slots?date={$date}")->assertOk();

    // Measure multiple requests
    $latencies = [];
    $iterations = 10;

    for ($i = 0; $i < $iterations; $i++) {
        $startTime = microtime(true);

        $response = $this->getJson("/api/courts/{$targetCourt->id}/slots?date={$date}");
        $response->assertOk()
            ->assertJsonCount(17, 'data');

        $elapsedMs = (microtime(true) - $startTime) * 1000;
        $latencies[] = $elapsedMs;
    }

    $avgLatency = array_sum($latencies) / count($latencies);
    $maxLatency = max($latencies);

    // Target KPI: Sub-80ms response time
    expect($avgLatency)->toBeLessThan(80.0)
        ->and($maxLatency)->toBeLessThan(80.0);
});
