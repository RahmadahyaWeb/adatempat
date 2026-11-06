<?php

namespace Tests\Feature\Slots;

use App\Enums\CancellationPolicy;
use App\Enums\SpaceCategory;
use App\Models\Business;
use App\Models\Schedule;
use App\Models\Space;
use App\Models\SpaceScheduleVersion;
use App\Models\User;
use App\Models\Venue;
use App\Services\Slots\SlotGeneratorService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

class SlotGeneratorServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Venue $venue;

    private Space $space;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();

        $business = Business::factory()->create([
            'user_id' => $this->owner->id,
            'name' => 'Arena Sport Nusantara',
        ]);

        $this->venue = Venue::factory()->approved()->create([
            'business_id' => $business->id,
            'name' => 'Arena Futsal Nusantara',
            'slug' => 'arena-futsal-nusantara',
            'city' => 'Samarinda',
            'address' => 'Jl. Pahlawan No. 1',
            'timezone' => 'Asia/Makassar',
            'contact_phone' => '081234567890',
            'is_published' => true,
        ]);

        $this->space = Space::factory()->create([
            'venue_id' => $this->venue->id,
            'name' => 'Lapangan 1',
            'slug' => 'arena-futsal-nusantara-lapangan-1',
            'category' => SpaceCategory::Futsal,
            'capacity' => 10,
            'base_price' => 120000,
            'cancellation_policy' => CancellationPolicy::FreeCancellation,
            'cancellation_cutoff_hours' => 6,
            'refund_percentage' => 100,
            'is_active' => true,
        ]);
    }

    private function createVersion(int $version = 1, string $effectiveFrom = '2026-11-01', ?string $effectiveTo = null): SpaceScheduleVersion
    {
        $v = new SpaceScheduleVersion;
        $v->space_id = $this->space->id;
        $v->version = $version;
        $v->effective_from = $effectiveFrom;
        $v->effective_to = $effectiveTo;
        $v->created_by = $this->owner->id;
        $v->save();

        return $v;
    }

    private function createSchedule(SpaceScheduleVersion $version, int $dayOfWeek, string $openTime, string $closeTime, int $duration = 60): Schedule
    {
        $s = new Schedule;
        $s->space_id = $this->space->id;
        $s->schedule_version_id = $version->id;
        $s->day_of_week = $dayOfWeek;
        $s->open_time = $openTime;
        $s->close_time = $closeTime;
        $s->slot_duration_minutes = $duration;
        $s->is_active = true;
        $s->save();

        return $s;
    }

    public function test_generates_canonical_grid_with_durations_30_60_90_120_minutes(): void
    {
        $service = new SlotGeneratorService;
        $now = CarbonImmutable::parse('2026-11-05 06:00:00', 'Asia/Makassar');
        $date = '2026-11-06'; // Friday, dayOfWeek = 5
        $dayOfWeek = CarbonImmutable::parse($date, 'Asia/Makassar')->dayOfWeek;

        $durations = [
            30 => ['expected_count' => 8, 'first_end' => '08:30', 'price' => 60000],
            60 => ['expected_count' => 4, 'first_end' => '09:00', 'price' => 120000],
            90 => ['expected_count' => 2, 'first_end' => '09:30', 'price' => 180000],
            120 => ['expected_count' => 2, 'first_end' => '10:00', 'price' => 240000],
        ];

        foreach ($durations as $duration => $expected) {
            $version = $this->createVersion($duration, '2026-11-01', null);
            $this->createSchedule($version, $dayOfWeek, '08:00:00', '12:00:00', $duration);

            $result = $service->generate($this->space, $date, $now);

            $this->assertSame($date, $result['date']);
            $this->assertSame('Asia/Makassar', $result['timezone']);
            $this->assertSame($duration, $result['slot_duration_minutes']);
            $this->assertCount($expected['expected_count'], $result['slots']);

            $this->assertSame('08:00', $result['slots'][0]['start_time']);
            $this->assertSame($expected['first_end'], $result['slots'][0]['end_time']);
            $this->assertSame($expected['price'], $result['slots'][0]['price']);
            $this->assertSame('available', $result['slots'][0]['status']);

            Schedule::query()->where('schedule_version_id', $version->id)->delete();
            $version->delete();
        }
    }

    public function test_enforces_30_day_horizon_boundaries(): void
    {
        $service = new SlotGeneratorService;
        $now = CarbonImmutable::parse('2026-11-05 10:00:00', 'Asia/Makassar');

        $version = $this->createVersion(1, '2026-10-01', null);
        foreach (range(0, 6) as $day) {
            $this->createSchedule($version, $day, '08:00:00', '12:00:00', 60);
        }

        // H+0 (Today): Accepted
        $todayResult = $service->generate($this->space, '2026-11-05', $now);
        $this->assertNotEmpty($todayResult['slots']);

        // H+30 (30 days from today): Accepted
        $h30Result = $service->generate($this->space, '2026-12-05', $now);
        $this->assertNotEmpty($h30Result['slots']);

        // H-1 (Past): Rejected
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Tanggal tidak boleh di masa lampau.');
        $service->generate($this->space, '2026-11-04', $now);
    }

    public function test_rejects_date_exceeding_30_days_horizon(): void
    {
        $service = new SlotGeneratorService;
        $now = CarbonImmutable::parse('2026-11-05 10:00:00', 'Asia/Makassar');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Tanggal melebihi batas horizon 30 hari.');
        $service->generate($this->space, '2026-12-06', $now); // H+31
    }

    public function test_rejects_invalid_date_format(): void
    {
        $service = new SlotGeneratorService;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Format tanggal harus Y-m-d.');
        $service->generate($this->space, '2026/11/05');
    }

    public function test_respects_venue_timezone_wita_vs_wib(): void
    {
        $service = new SlotGeneratorService;

        // UTC: 2026-11-05 16:30:00
        // WIB (UTC+7): 2026-11-05 23:30:00 (still Nov 5)
        // WITA (UTC+8, venue timezone): 2026-11-06 00:30:00 (already Nov 6)
        $nowUtc = CarbonImmutable::parse('2026-11-05 16:30:00', 'UTC');

        $version = $this->createVersion(1, '2026-11-01', null);
        foreach (range(0, 6) as $day) {
            $this->createSchedule($version, $day, '08:00:00', '12:00:00', 60);
        }

        // For venue in WITA, 2026-11-05 is in the past because local time is 2026-11-06 00:30
        try {
            $service->generate($this->space, '2026-11-05', $nowUtc);
            $this->fail('Should have rejected 2026-11-05 as past in venue local timezone.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('Tanggal tidak boleh di masa lampau.', $e->getMessage());
        }

        // 2026-11-06 is local today (H+0) and must succeed
        $result = $service->generate($this->space, '2026-11-06', $nowUtc);
        $this->assertSame('2026-11-06', $result['date']);
        $this->assertSame('Asia/Makassar', $result['timezone']);
        $this->assertNotEmpty($result['slots']);
    }

    public function test_respects_schedule_version_transitions(): void
    {
        $service = new SlotGeneratorService;
        $now = CarbonImmutable::parse('2026-11-01 06:00:00', 'Asia/Makassar');

        // Version 1: 60 minutes, effective 2026-11-01 to 2026-11-15
        $v1 = $this->createVersion(1, '2026-11-01', '2026-11-15');
        // Version 2: 30 minutes, effective 2026-11-16 onwards
        $v2 = $this->createVersion(2, '2026-11-16', null);

        foreach (range(0, 6) as $day) {
            $this->createSchedule($v1, $day, '08:00:00', '12:00:00', 60);
            $this->createSchedule($v2, $day, '08:00:00', '12:00:00', 30);
        }

        // On 2026-11-10 (under Version 1): 60 min slots
        $v1Result = $service->generate($this->space, '2026-11-10', $now);
        $this->assertSame(60, $v1Result['slot_duration_minutes']);
        $this->assertCount(4, $v1Result['slots']);

        // On 2026-11-20 (under Version 2): 30 min slots
        $v2Result = $service->generate($this->space, '2026-11-20', $now);
        $this->assertSame(30, $v2Result['slot_duration_minutes']);
        $this->assertCount(8, $v2Result['slots']);
    }

    public function test_marks_booked_slots_from_database(): void
    {
        $service = new SlotGeneratorService;
        $now = CarbonImmutable::parse('2026-11-05 06:00:00', 'Asia/Makassar');
        $date = '2026-11-10';

        $version = $this->createVersion(1, '2026-11-01', null);
        $this->createSchedule($version, CarbonImmutable::parse($date)->dayOfWeek, '08:00:00', '12:00:00', 60);

        // Insert booking_slot for 09:00 - 10:00
        DB::table('booking_slots')->insert([
            'booking_id' => 999,
            'space_id' => $this->space->id,
            'schedule_version_id' => $version->id,
            'slot_date' => $date,
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'price' => 120000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = $service->generate($this->space, $date, $now);

        $this->assertSame('available', $result['slots'][0]['status']); // 08:00 - 09:00
        $this->assertSame('booked', $result['slots'][1]['status']);    // 09:00 - 10:00
        $this->assertSame('available', $result['slots'][2]['status']); // 10:00 - 11:00
        $this->assertSame('available', $result['slots'][3]['status']); // 11:00 - 12:00
    }

    public function test_marks_past_slots_for_current_day(): void
    {
        $service = new SlotGeneratorService;
        // Fix current time to 10:15
        $now = CarbonImmutable::parse('2026-11-05 10:15:00', 'Asia/Makassar');
        $date = '2026-11-05';

        $version = $this->createVersion(1, '2026-11-01', null);
        $this->createSchedule($version, CarbonImmutable::parse($date)->dayOfWeek, '08:00:00', '13:00:00', 60);

        $result = $service->generate($this->space, $date, $now);

        // 08:00 - 09:00 -> started <= 10:15 -> past
        $this->assertSame('past', $result['slots'][0]['status']);
        // 09:00 - 10:00 -> started <= 10:15 -> past
        $this->assertSame('past', $result['slots'][1]['status']);
        // 10:00 - 11:00 -> started <= 10:15 -> past
        $this->assertSame('past', $result['slots'][2]['status']);
        // 11:00 - 12:00 -> starts > 10:15 -> available
        $this->assertSame('available', $result['slots'][3]['status']);
        // 12:00 - 13:00 -> starts > 10:15 -> available
        $this->assertSame('available', $result['slots'][4]['status']);
    }

    public function test_returns_empty_slots_when_no_active_schedule_or_version(): void
    {
        $service = new SlotGeneratorService;
        $now = CarbonImmutable::parse('2026-11-05 06:00:00', 'Asia/Makassar');

        // No schedule version exists
        $result = $service->generate($this->space, '2026-11-10', $now);
        $this->assertSame([], $result['slots']);
        $this->assertNull($result['slot_duration_minutes']);
    }
}
