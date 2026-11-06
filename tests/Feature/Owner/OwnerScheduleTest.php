<?php

namespace Tests\Feature\Owner;

use App\Enums\SpaceCategory;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Business;
use App\Models\Schedule;
use App\Models\Space;
use App\Models\SpaceScheduleVersion;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OwnerScheduleTest extends TestCase
{
    use RefreshDatabase;

    private User $ownerA;

    private Business $businessA;

    private Venue $venueA;

    private Space $spaceA;

    private User $ownerB;

    private Business $businessB;

    private Venue $venueB;

    private Space $spaceB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ownerA = User::factory()->create([
            'role' => UserRole::Owner,
            'email' => 'owner.a@adatempat.test',
        ]);

        $this->businessA = Business::factory()->create([
            'user_id' => $this->ownerA->id,
            'name' => 'Alpha Sports',
        ]);

        $this->venueA = Venue::factory()->create([
            'business_id' => $this->businessA->id,
            'name' => 'Alpha Venue',
            'slug' => 'alpha-venue',
        ]);

        $this->spaceA = Space::factory()->create([
            'venue_id' => $this->venueA->id,
            'name' => 'Alpha Court 1',
            'slug' => 'alpha-court-1',
            'category' => SpaceCategory::Badminton,
            'base_price' => 100000,
        ]);

        $versionA = SpaceScheduleVersion::factory()->create([
            'space_id' => $this->spaceA->id,
            'version' => 1,
            'effective_from' => today(),
            'created_by' => $this->ownerA->id,
        ]);

        for ($day = 0; $day <= 6; $day++) {
            Schedule::factory()->create([
                'space_id' => $this->spaceA->id,
                'schedule_version_id' => $versionA->id,
                'day_of_week' => $day,
                'open_time' => '08:00',
                'close_time' => '22:00',
                'slot_duration_minutes' => 60,
                'is_active' => true,
            ]);
        }

        $this->ownerB = User::factory()->create([
            'role' => UserRole::Owner,
            'email' => 'owner.b@adatempat.test',
        ]);

        $this->businessB = Business::factory()->create([
            'user_id' => $this->ownerB->id,
            'name' => 'Beta Sports',
        ]);

        $this->venueB = Venue::factory()->create([
            'business_id' => $this->businessB->id,
            'name' => 'Beta Venue',
            'slug' => 'beta-venue',
        ]);

        $this->spaceB = Space::factory()->create([
            'venue_id' => $this->venueB->id,
            'name' => 'Beta Court 1',
            'slug' => 'beta-court-1',
        ]);
    }

    public function test_guest_cannot_view_or_update_schedules(): void
    {
        $response = $this->get(route('owner.venues.spaces.schedules.index', [$this->venueA, $this->spaceA]));
        $response->assertRedirect(route('login'));

        $putResponse = $this->put(route('owner.venues.spaces.schedules.update', [$this->venueA, $this->spaceA]), []);
        $putResponse->assertRedirect(route('login'));
    }

    public function test_owner_can_view_space_schedules(): void
    {
        $response = $this->actingAs($this->ownerA)->get(
            route('owner.venues.spaces.schedules.index', [$this->venueA, $this->spaceA])
        );

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('owner/spaces/schedules')
            ->has('venue')
            ->has('space')
            ->has('version')
            ->where('version.version', 1)
            ->has('schedules', 7)
            ->where('hasFutureBookings', false)
        );
    }

    public function test_owner_cannot_view_schedules_of_other_owner_space(): void
    {
        $response = $this->actingAs($this->ownerA)->get(
            route('owner.venues.spaces.schedules.index', [$this->venueB, $this->spaceB])
        );
        $response->assertForbidden();
    }

    public function test_owner_can_update_schedules_without_future_bookings(): void
    {
        $newSchedules = [
            [
                'day_of_week' => 1,
                'open_time' => '09:00',
                'close_time' => '21:00',
                'slot_duration_minutes' => 60,
                'is_active' => true,
            ],
            [
                'day_of_week' => 2,
                'open_time' => '09:00',
                'close_time' => '21:00',
                'slot_duration_minutes' => 60,
                'is_active' => true,
            ],
        ];

        $response = $this->actingAs($this->ownerA)->put(
            route('owner.venues.spaces.schedules.update', [$this->venueA, $this->spaceA]),
            ['schedules' => $newSchedules]
        );

        $response->assertRedirect(route('owner.venues.spaces.schedules.index', [$this->venueA, $this->spaceA]));

        // Version should remain 1
        $this->assertDatabaseCount('space_schedule_versions', 1);
        $this->assertDatabaseHas('space_schedule_versions', [
            'space_id' => $this->spaceA->id,
            'version' => 1,
        ]);

        // Schedules count in DB is updated to 2
        $this->assertDatabaseCount('schedules', 2);
        $this->assertDatabaseHas('schedules', [
            'space_id' => $this->spaceA->id,
            'day_of_week' => 1,
            'open_time' => '09:00',
            'close_time' => '21:00',
        ]);
    }

    public function test_owner_updating_schedules_with_future_bookings_creates_new_version(): void
    {
        $customer = User::factory()->create();

        // Create future booking
        Booking::factory()->create([
            'venue_id' => $this->venueA->id,
            'space_id' => $this->spaceA->id,
            'user_id' => $customer->id,
            'booking_date' => today()->addDays(2),
            'status' => 'confirmed',
        ]);

        $newSchedules = [
            [
                'day_of_week' => 1,
                'open_time' => '10:00',
                'close_time' => '20:00',
                'slot_duration_minutes' => 60,
                'is_active' => true,
            ],
        ];

        $response = $this->actingAs($this->ownerA)->put(
            route('owner.venues.spaces.schedules.update', [$this->venueA, $this->spaceA]),
            ['schedules' => $newSchedules]
        );

        $response->assertRedirect(route('owner.venues.spaces.schedules.index', [$this->venueA, $this->spaceA]));

        // There should now be 2 versions (v1 archived, v2 active)
        $v1 = SpaceScheduleVersion::where('space_id', $this->spaceA->id)->where('version', 1)->first();
        $this->assertNotNull($v1);
        $this->assertEquals(today()->toDateString(), $v1->effective_to?->toDateString());

        $v2 = SpaceScheduleVersion::where('space_id', $this->spaceA->id)->where('version', 2)->first();
        $this->assertNotNull($v2);
        $this->assertEquals(today()->toDateString(), $v2->effective_from->toDateString());
    }

    public function test_schedule_validation_rejects_close_time_before_open_time(): void
    {
        $invalidSchedules = [
            [
                'day_of_week' => 1,
                'open_time' => '20:00',
                'close_time' => '08:00', // Invalid: before open_time
                'slot_duration_minutes' => 60,
                'is_active' => true,
            ],
        ];

        $response = $this->actingAs($this->ownerA)->put(
            route('owner.venues.spaces.schedules.update', [$this->venueA, $this->spaceA]),
            ['schedules' => $invalidSchedules]
        );

        $response->assertSessionHasErrors('schedules.0.close_time');
    }

    public function test_schedule_validation_rejects_invalid_slot_duration(): void
    {
        $invalidSchedules = [
            [
                'day_of_week' => 1,
                'open_time' => '08:00',
                'close_time' => '22:00',
                'slot_duration_minutes' => 45, // Invalid: not in 30, 60, 90, 120
                'is_active' => true,
            ],
        ];

        $response = $this->actingAs($this->ownerA)->put(
            route('owner.venues.spaces.schedules.update', [$this->venueA, $this->spaceA]),
            ['schedules' => $invalidSchedules]
        );

        $response->assertSessionHasErrors('schedules.0.slot_duration_minutes');
    }

    public function test_schedule_validation_rejects_overlapping_schedules_on_same_day(): void
    {
        $overlappingSchedules = [
            [
                'day_of_week' => 1,
                'open_time' => '08:00',
                'close_time' => '14:00',
                'slot_duration_minutes' => 60,
                'is_active' => true,
            ],
            [
                'day_of_week' => 1,
                'open_time' => '12:00', // Overlaps with 08:00 - 14:00
                'close_time' => '18:00',
                'slot_duration_minutes' => 60,
                'is_active' => true,
            ],
        ];

        $response = $this->actingAs($this->ownerA)->put(
            route('owner.venues.spaces.schedules.update', [$this->venueA, $this->spaceA]),
            ['schedules' => $overlappingSchedules]
        );

        $response->assertSessionHasErrors('schedules.1.open_time');
    }
}
