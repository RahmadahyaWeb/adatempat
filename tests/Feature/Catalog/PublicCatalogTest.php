<?php

namespace Tests\Feature\Catalog;

use App\Models\Amenity;
use App\Models\Schedule;
use App\Models\Space;
use App\Models\SpaceAddOn;
use App\Models\SpaceScheduleVersion;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class PublicCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_home_lists_only_published_venues(): void
    {
        $published = Venue::factory()->approved()->create(['city' => 'Samarinda']);
        Space::factory()->for($published)->create(['category' => 'futsal', 'base_price' => 150000]);

        Venue::factory()->create(['city' => 'Jakarta']);
        Venue::factory()->approved()->create(['city' => 'Bandung', 'is_published' => false]);

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('public/home', false)
                ->has('venues', 1)
                ->where('venues.0.slug', $published->slug)
                ->where('venues.0.starting_price', 150000)
                ->has('cities', 1)
                ->where('cities.0', 'Samarinda')
                ->has('categories', 1)
                ->where('categories.0.value', 'futsal')
                ->where('categories.0.count', 1));
    }

    public function test_browse_filters_by_city_category_and_price(): void
    {
        $samarinda = Venue::factory()->approved()->create(['name' => 'Borneo Stadium', 'city' => 'Samarinda']);
        $futsal = Space::factory()->for($samarinda)->create(['name' => 'Lapangan Futsal A', 'category' => 'futsal', 'base_price' => 150000]);
        $badminton = Space::factory()->for($samarinda)->create(['name' => 'Court Badminton 1', 'category' => 'badminton', 'base_price' => 60000]);

        $jakarta = Venue::factory()->approved()->create(['name' => 'Menara Jakarta', 'city' => 'Jakarta']);
        $meeting = Space::factory()->for($jakarta)->create(['name' => 'Ruang Meeting VIP', 'category' => 'meeting', 'base_price' => 100000]);

        $this->get(route('spaces.index', ['city' => 'Samarinda']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('public/spaces/index', false)
                ->has('spaces.data', 2)
                ->where('spaces.data.0.slug', $badminton->slug)
                ->where('spaces.data.1.slug', $futsal->slug));

        $this->get(route('spaces.index', ['category' => 'meeting']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('spaces.data', 1)
                ->where('spaces.data.0.slug', $meeting->slug));

        $this->get(route('spaces.index', ['max_price' => 100000]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('spaces.data', 2));

        $this->get(route('spaces.index', ['min_price' => 120000]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('spaces.data', 1)
                ->where('spaces.data.0.slug', $futsal->slug));

        $this->get(route('spaces.index', ['city' => 'Samarinda', 'category' => 'futsal']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('spaces.data', 1)
                ->where('spaces.data.0.slug', $futsal->slug));

        $this->get(route('spaces.index', ['q' => 'Futsal']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('spaces.data', 1)
                ->where('spaces.data.0.slug', $futsal->slug));

        $this->get(route('spaces.index', ['q' => 'Jakarta']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('spaces.data', 1)
                ->where('spaces.data.0.slug', $meeting->slug));
    }

    public function test_browse_sorts_by_price(): void
    {
        $venue = Venue::factory()->approved()->create();
        $expensive = Space::factory()->for($venue)->create(['base_price' => 200000]);
        $cheap = Space::factory()->for($venue)->create(['base_price' => 50000]);

        $this->get(route('spaces.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('sort', 'price_asc')
                ->where('spaces.data.0.slug', $cheap->slug));

        $this->get(route('spaces.index', ['sort' => 'price_desc']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('sort', 'price_desc')
                ->where('spaces.data.0.slug', $expensive->slug));
    }

    public function test_browse_filters_by_weekday_schedule(): void
    {
        $venue = Venue::factory()->approved()->create();
        $space = Space::factory()->for($venue)->create();
        $version = SpaceScheduleVersion::factory()->for($space)->create([
            'version' => 1,
            'created_by' => User::factory(),
        ]);
        Schedule::factory()->create([
            'space_id' => $space->id,
            'schedule_version_id' => $version->id,
            'day_of_week' => 1,
        ]);

        $this->get(route('spaces.index', ['date' => '2026-11-09']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('spaces.data', 1)
                ->where('spaces.data.0.slug', $space->slug));

        $this->get(route('spaces.index', ['date' => '2026-11-10']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('spaces.data', 0));
    }

    public function test_browse_excludes_pending_venues_and_inactive_spaces(): void
    {
        $pendingVenue = Venue::factory()->create();
        Space::factory()->for($pendingVenue)->create();

        $unpublishedVenue = Venue::factory()->approved()->create(['is_published' => false]);
        Space::factory()->for($unpublishedVenue)->create();

        $publishedVenue = Venue::factory()->approved()->create();
        Space::factory()->for($publishedVenue)->create(['slug' => 'ruang-tampil']);
        Space::factory()->for($publishedVenue)->create(['slug' => 'ruang-nonaktif', 'is_active' => false]);

        $this->get(route('spaces.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('spaces.data', 1)
                ->where('spaces.data.0.slug', 'ruang-tampil'));
    }

    public function test_venue_profile_is_only_visible_when_published(): void
    {
        $venue = Venue::factory()->approved()->create();
        $space = Space::factory()->for($venue)->create();

        $this->get(route('venues.show', $venue))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('public/venues/show', false)
                ->where('venue.slug', $venue->slug)
                ->has('venue.spaces', 1)
                ->where('venue.spaces.0.slug', $space->slug));

        $pending = Venue::factory()->create();
        $this->get(route('venues.show', $pending))->assertNotFound();

        $unpublished = Venue::factory()->approved()->create(['is_published' => false]);
        $this->get(route('venues.show', $unpublished))->assertNotFound();
    }

    public function test_space_detail_returns_amenities_addons_and_operating_hours(): void
    {
        $venue = Venue::factory()->approved()->create();
        $space = Space::factory()->for($venue)->create();
        $amenity = Amenity::factory()->create(['name' => 'WiFi']);
        $space->amenities()->attach($amenity);
        SpaceAddOn::factory()->for($space)->create(['name' => 'Bola', 'is_active' => true]);
        SpaceAddOn::factory()->for($space)->create(['name' => 'Rompi', 'is_active' => false]);

        $version = SpaceScheduleVersion::factory()->for($space)->create([
            'version' => 1,
            'effective_from' => now()->subDay()->toDateString(),
            'created_by' => User::factory(),
        ]);

        foreach (range(0, 6) as $dayOfWeek) {
            Schedule::factory()->create([
                'space_id' => $space->id,
                'schedule_version_id' => $version->id,
                'day_of_week' => $dayOfWeek,
            ]);
        }

        $this->get(route('spaces.show', $space))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('public/spaces/show', false)
                ->where('space.slug', $space->slug)
                ->where('venue.slug', $venue->slug)
                ->has('space.amenities', 1)
                ->has('space.add_ons', 1)
                ->where('space.add_ons.0.name', 'Bola')
                ->has('space.operating_hours', 7));

        $pendingVenue = Venue::factory()->create();
        $hiddenSpace = Space::factory()->for($pendingVenue)->create();

        $this->get(route('spaces.show', $hiddenSpace))->assertNotFound();
    }

    public function test_browse_query_count_is_bounded(): void
    {
        $venue = Venue::factory()->approved()->create();
        Space::factory()->count(12)->for($venue)->create();

        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        $this->get(route('spaces.index'))->assertOk();

        $this->assertLessThanOrEqual(12, $queries);
    }
}
