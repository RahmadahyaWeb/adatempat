<?php

namespace Tests\Feature\Catalog;

use App\Enums\AddOnPricingType;
use App\Enums\CancellationPolicy;
use App\Enums\SpaceCategory;
use App\Enums\VenueApprovalStatus;
use App\Models\Amenity;
use App\Models\Schedule;
use App\Models\Space;
use App\Models\SpaceAddOn;
use App\Models\SpaceScheduleVersion;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_seed_creates_four_published_venues_with_spaces_and_schedules(): void
    {
        $this->seed();

        $venues = Venue::query()->with('spaces.schedules')->get();

        $this->assertCount(4, $venues);
        $this->assertSame(
            4,
            $venues->where('approval_status', VenueApprovalStatus::Approved)->where('is_published', true)->count(),
        );

        $spaceCount = 0;
        $scheduleCount = 0;

        foreach ($venues as $venue) {
            $this->assertNotNull($venue->approved_at);
            $this->assertNotEmpty($venue->spaces);

            foreach ($venue->spaces as $space) {
                $this->assertTrue($space->is_active);
                $spaceCount++;
                $scheduleCount += $space->schedules->count();
            }
        }

        $this->assertSame(8, $spaceCount);
        $this->assertSame(56, $scheduleCount);
    }

    public function test_catalog_seed_is_idempotent(): void
    {
        $this->seed();
        $this->seed();

        $this->assertSame(4, Venue::query()->count());
        $this->assertSame(8, Space::query()->count());
        $this->assertSame(56, Schedule::query()->count());
        $this->assertSame(15, SpaceAddOn::query()->count());
        $this->assertSame(8, Amenity::query()->count());
    }

    public function test_venue_and_space_relationships(): void
    {
        $venue = Venue::factory()->approved()->create();
        $space = Space::factory()->for($venue)->create();
        $amenity = Amenity::factory()->create();
        $space->amenities()->attach($amenity);
        $addOn = SpaceAddOn::factory()->for($space)->create();
        $version = SpaceScheduleVersion::factory()->for($space)->create(['created_by' => User::factory()]);
        $schedule = Schedule::factory()->create([
            'space_id' => $space->id,
            'schedule_version_id' => $version->id,
        ]);

        $this->assertTrue($space->venue->is($venue));
        $this->assertSame($venue->business_id, $venue->business->id);
        $this->assertTrue($space->amenities->first()->is($amenity));
        $this->assertTrue($space->addOns->first()->is($addOn));
        $this->assertTrue($version->schedules->first()->is($schedule));
        $this->assertTrue($schedule->version->is($version));
    }

    public function test_catalog_enums_are_cast(): void
    {
        $venue = Venue::factory()->create(['approval_status' => 'rejected']);
        $space = Space::factory()->create([
            'category' => 'futsal',
            'cancellation_policy' => 'free_cancellation',
        ]);
        $addOn = SpaceAddOn::factory()->create(['pricing_type' => 'per_hour']);

        $this->assertSame(VenueApprovalStatus::Rejected, $venue->approval_status);
        $this->assertSame(SpaceCategory::Futsal, $space->category);
        $this->assertSame(CancellationPolicy::FreeCancellation, $space->cancellation_policy);
        $this->assertSame(AddOnPricingType::PerHour, $addOn->pricing_type);
    }

    public function test_space_slug_must_be_unique(): void
    {
        Space::factory()->create(['slug' => 'lapangan-sama']);

        $this->expectException(QueryException::class);

        Space::factory()->create(['slug' => 'lapangan-sama']);
    }

    public function test_schedule_version_is_unique_per_space(): void
    {
        $space = Space::factory()->create();
        SpaceScheduleVersion::factory()->for($space)->create(['version' => 1]);

        $this->expectException(QueryException::class);

        SpaceScheduleVersion::factory()->for($space)->create(['version' => 1]);
    }
}
