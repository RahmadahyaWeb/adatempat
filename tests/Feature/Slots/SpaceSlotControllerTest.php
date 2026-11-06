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
use App\Services\Slots\SlotReservationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SpaceSlotControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Venue $venue;

    private Space $space;

    private SpaceScheduleVersion $version;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();

        $business = Business::factory()->create([
            'user_id' => $this->owner->id,
            'name' => 'Arena Sport',
        ]);

        $this->venue = Venue::factory()->approved()->create([
            'business_id' => $business->id,
            'name' => 'Arena Futsal',
            'city' => 'Samarinda',
            'timezone' => 'Asia/Makassar',
            'is_published' => true,
        ]);

        $this->space = Space::factory()->create([
            'venue_id' => $this->venue->id,
            'name' => 'Lapangan 1',
            'category' => SpaceCategory::Futsal,
            'base_price' => 100000,
            'cancellation_policy' => CancellationPolicy::FreeCancellation,
            'is_active' => true,
        ]);

        $this->version = SpaceScheduleVersion::factory()->create([
            'space_id' => $this->space->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'effective_to' => null,
            'created_by' => $this->owner->id,
        ]);

        foreach (range(0, 6) as $day) {
            Schedule::factory()->create([
                'space_id' => $this->space->id,
                'schedule_version_id' => $this->version->id,
                'day_of_week' => $day,
                'open_time' => '08:00:00',
                'close_time' => '12:00:00',
                'slot_duration_minutes' => 60,
                'is_active' => true,
            ]);
        }
    }

    public function test_can_fetch_slots_for_valid_date(): void
    {
        $today = CarbonImmutable::now('Asia/Makassar')->toDateString();

        $response = $this->getJson(route('spaces.slots', [
            'space' => $this->space->slug,
            'date' => $today,
        ]));

        $response->assertOk()
            ->assertJsonStructure([
                'date',
                'timezone',
                'slot_duration_minutes',
                'slots' => [
                    '*' => ['start_time', 'end_time', 'price', 'status'],
                ],
            ]);

        $this->assertSame($today, $response->json('date'));
        $this->assertSame('Asia/Makassar', $response->json('timezone'));
        $this->assertSame(60, $response->json('slot_duration_minutes'));
        $this->assertCount(4, $response->json('slots'));
    }

    public function test_caches_slot_results_in_redis(): void
    {
        $targetDate = CarbonImmutable::now('Asia/Makassar')->addDays(3)->toDateString();
        $cacheKey = "slots:space:{$this->space->id}:date:{$targetDate}";

        Cache::forget($cacheKey);
        $this->assertFalse(Cache::has($cacheKey));

        $response = $this->getJson(route('spaces.slots', [
            'space' => $this->space->slug,
            'date' => $targetDate,
        ]));

        $response->assertOk();
        $this->assertTrue(Cache::has($cacheKey));

        $cached = Cache::get($cacheKey);
        $this->assertIsArray($cached);
        $this->assertSame($targetDate, $cached['date']);
    }

    public function test_booking_reservation_invalidates_cache(): void
    {
        $targetDate = CarbonImmutable::now('Asia/Makassar')->addDays(2)->toDateString();
        $cacheKey = "slots:space:{$this->space->id}:date:{$targetDate}";

        // First fetch to populate cache
        $this->getJson(route('spaces.slots', [
            'space' => $this->space->slug,
            'date' => $targetDate,
        ]))->assertOk();

        $this->assertTrue(Cache::has($cacheKey));

        // Reserve a slot via SlotReservationService
        $reservationService = new SlotReservationService;
        $reservationService->reserve(101, $this->space->id, $targetDate, [
            ['start_time' => '08:00', 'end_time' => '09:00', 'price' => 100000],
        ]);

        // Cache must have been invalidated
        $this->assertFalse(Cache::has($cacheKey));

        // Fetching again yields fresh slot status
        $response = $this->getJson(route('spaces.slots', [
            'space' => $this->space->slug,
            'date' => $targetDate,
        ]));

        $response->assertOk();
        $slots = $response->json('slots');
        $this->assertSame('booked', $slots[0]['status']);
    }

    public function test_rejects_past_date(): void
    {
        $yesterday = CarbonImmutable::now('Asia/Makassar')->subDay()->toDateString();

        $response = $this->getJson(route('spaces.slots', [
            'space' => $this->space->slug,
            'date' => $yesterday,
        ]));

        $response->assertStatus(422)
            ->assertJson(['message' => 'Tanggal tidak boleh di masa lampau.']);
    }

    public function test_rejects_date_exceeding_30_days(): void
    {
        $farFuture = CarbonImmutable::now('Asia/Makassar')->addDays(31)->toDateString();

        $response = $this->getJson(route('spaces.slots', [
            'space' => $this->space->slug,
            'date' => $farFuture,
        ]));

        $response->assertStatus(422)
            ->assertJson(['message' => 'Tanggal melebihi batas horizon 30 hari.']);
    }

    public function test_rejects_malformed_date(): void
    {
        $response = $this->getJson(route('spaces.slots', [
            'space' => $this->space->slug,
            'date' => 'invalid-date',
        ]));

        $response->assertStatus(422)
            ->assertJson(['message' => 'Format tanggal harus YYYY-MM-DD.']);
    }

    public function test_returns_404_if_space_inactive(): void
    {
        $this->space->update(['is_active' => false]);

        $response = $this->getJson(route('spaces.slots', [
            'space' => $this->space->slug,
        ]));

        $response->assertNotFound();
    }
}
