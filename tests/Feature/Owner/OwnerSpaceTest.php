<?php

namespace Tests\Feature\Owner;

use App\Enums\SpaceCategory;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Business;
use App\Models\Space;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OwnerSpaceTest extends TestCase
{
    use RefreshDatabase;

    private User $ownerA;

    private Business $businessA;

    private Venue $venueA;

    private User $ownerB;

    private Business $businessB;

    private Venue $venueB;

    private User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ownerA = User::factory()->create([
            'role' => UserRole::Owner,
            'email' => 'owner.a@adatempat.test',
        ]);

        $this->businessA = Business::factory()->create([
            'user_id' => $this->ownerA->id,
            'name' => 'Alpha Sports Center',
        ]);

        $this->venueA = Venue::factory()->create([
            'business_id' => $this->businessA->id,
            'name' => 'Alpha Arena',
            'slug' => 'alpha-arena',
        ]);

        $this->ownerB = User::factory()->create([
            'role' => UserRole::Owner,
            'email' => 'owner.b@adatempat.test',
        ]);

        $this->businessB = Business::factory()->create([
            'user_id' => $this->ownerB->id,
            'name' => 'Beta Arena Group',
        ]);

        $this->venueB = Venue::factory()->create([
            'business_id' => $this->businessB->id,
            'name' => 'Beta Sports Hub',
            'slug' => 'beta-sports-hub',
        ]);

        $this->superadmin = User::factory()->create([
            'role' => UserRole::Superadmin,
            'email' => 'superadmin@adatempat.test',
        ]);
    }

    public function test_guest_cannot_access_spaces(): void
    {
        $response = $this->get(route('owner.venues.spaces.index', $this->venueA));
        $response->assertRedirect(route('login'));
    }

    public function test_owner_can_view_own_venue_spaces(): void
    {
        Space::factory()->create([
            'venue_id' => $this->venueA->id,
            'name' => 'Lapangan Futsal 1',
            'slug' => 'lapangan-futsal-1',
            'category' => SpaceCategory::Futsal,
            'base_price' => 150000,
        ]);

        $response = $this->actingAs($this->ownerA)->get(route('owner.venues.spaces.index', $this->venueA));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('owner/spaces/index')
            ->has('venue')
            ->has('spaces.data', 1)
            ->where('spaces.data.0.name', 'Lapangan Futsal 1')
            ->where('spaces.data.0.base_price', 150000)
        );
    }

    public function test_owner_cannot_view_other_owner_venue_spaces(): void
    {
        $response = $this->actingAs($this->ownerA)->get(route('owner.venues.spaces.index', $this->venueB));
        $response->assertForbidden();
    }

    public function test_owner_can_create_space_with_auto_generated_slug_and_default_schedules(): void
    {
        $payload = [
            'name' => 'Lapangan Badminton A1',
            'category' => 'badminton',
            'capacity' => 8,
            'base_price' => 120000,
            'instant_book' => true,
            'cancellation_policy' => 'free_cancellation',
            'cancellation_cutoff_hours' => 6,
            'refund_percentage' => 100,
            'is_active' => true,
        ];

        $response = $this->actingAs($this->ownerA)->post(route('owner.venues.spaces.store', $this->venueA), $payload);

        $response->assertRedirect(route('owner.venues.spaces.index', $this->venueA));

        $this->assertDatabaseHas('spaces', [
            'venue_id' => $this->venueA->id,
            'name' => 'Lapangan Badminton A1',
            'slug' => 'lapangan-badminton-a1',
            'category' => 'badminton',
            'capacity' => 8,
            'base_price' => 120000,
            'instant_book' => true,
        ]);

        /** @var Space $space */
        $space = Space::where('slug', 'lapangan-badminton-a1')->first();
        $this->assertNotNull($space);

        // Assert v1 schedule version created
        $this->assertDatabaseHas('space_schedule_versions', [
            'space_id' => $space->id,
            'version' => 1,
            'created_by' => $this->ownerA->id,
        ]);

        // Assert 7 days of default schedules created
        $this->assertDatabaseCount('schedules', 7);
    }

    public function test_owner_cannot_create_space_in_other_owner_venue(): void
    {
        $payload = [
            'name' => 'Unauthorized Space',
            'category' => 'futsal',
            'capacity' => 10,
            'base_price' => 100000,
            'instant_book' => true,
            'cancellation_policy' => 'free_cancellation',
            'cancellation_cutoff_hours' => 6,
            'refund_percentage' => 100,
            'is_active' => true,
        ];

        $response = $this->actingAs($this->ownerA)->post(route('owner.venues.spaces.store', $this->venueB), $payload);
        $response->assertForbidden();

        $this->assertDatabaseMissing('spaces', [
            'name' => 'Unauthorized Space',
        ]);
    }

    public function test_store_space_validation_errors(): void
    {
        $response = $this->actingAs($this->ownerA)->post(route('owner.venues.spaces.store', $this->venueA), []);

        $response->assertSessionHasErrors([
            'name',
            'category',
            'base_price',
            'instant_book',
            'cancellation_policy',
            'cancellation_cutoff_hours',
            'refund_percentage',
        ]);
    }

    public function test_owner_can_update_space(): void
    {
        $space = Space::factory()->create([
            'venue_id' => $this->venueA->id,
            'name' => 'Lapangan Tenis Outdoor',
            'slug' => 'lapangan-tenis-outdoor',
            'category' => SpaceCategory::Tennis,
            'base_price' => 200000,
        ]);

        $payload = [
            'name' => 'Lapangan Tenis Outdoor All-Weather',
            'category' => 'tennis',
            'capacity' => 4,
            'base_price' => 250000,
            'instant_book' => false,
            'cancellation_policy' => 'partial_refund',
            'cancellation_cutoff_hours' => 12,
            'refund_percentage' => 50,
            'is_active' => true,
        ];

        $response = $this->actingAs($this->ownerA)->put(
            route('owner.venues.spaces.update', [$this->venueA, $space]),
            $payload
        );

        $response->assertRedirect(route('owner.venues.spaces.index', $this->venueA));

        $this->assertDatabaseHas('spaces', [
            'id' => $space->id,
            'name' => 'Lapangan Tenis Outdoor All-Weather',
            'slug' => 'lapangan-tenis-outdoor-all-weather',
            'base_price' => 250000,
            'instant_book' => false,
            'cancellation_policy' => 'partial_refund',
        ]);
    }

    public function test_owner_cannot_update_space_belonging_to_other_venue(): void
    {
        $spaceB = Space::factory()->create([
            'venue_id' => $this->venueB->id,
            'name' => 'Space Beta 1',
            'slug' => 'space-beta-1',
        ]);

        $payload = [
            'name' => 'Hacked Space',
            'category' => 'futsal',
            'capacity' => 10,
            'base_price' => 100000,
            'instant_book' => true,
            'cancellation_policy' => 'free_cancellation',
            'cancellation_cutoff_hours' => 6,
            'refund_percentage' => 100,
            'is_active' => true,
        ];

        // Owner A tries to update Space B via Venue B -> 403
        $response = $this->actingAs($this->ownerA)->put(
            route('owner.venues.spaces.update', [$this->venueB, $spaceB]),
            $payload
        );
        $response->assertForbidden();

        // Cross-venue attack: Owner A puts Space B in Venue A URL -> 404
        $responseCross = $this->actingAs($this->ownerA)->put(
            route('owner.venues.spaces.update', [$this->venueA, $spaceB]),
            $payload
        );
        $responseCross->assertNotFound();
    }

    public function test_owner_can_delete_space_without_bookings(): void
    {
        $space = Space::factory()->create([
            'venue_id' => $this->venueA->id,
            'name' => 'Space Tanpa Booking',
            'slug' => 'space-tanpa-booking',
        ]);

        $response = $this->actingAs($this->ownerA)->delete(
            route('owner.venues.spaces.destroy', [$this->venueA, $space])
        );

        $response->assertRedirect(route('owner.venues.spaces.index', $this->venueA));
        $this->assertDatabaseMissing('spaces', ['id' => $space->id]);
    }

    public function test_owner_cannot_delete_space_with_bookings(): void
    {
        $space = Space::factory()->create([
            'venue_id' => $this->venueA->id,
            'name' => 'Space Dengan Riwayat Booking',
            'slug' => 'space-dengan-booking',
        ]);

        $customer = User::factory()->create();

        Booking::factory()->create([
            'venue_id' => $this->venueA->id,
            'space_id' => $space->id,
            'user_id' => $customer->id,
        ]);

        $response = $this->actingAs($this->ownerA)->delete(
            route('owner.venues.spaces.destroy', [$this->venueA, $space])
        );

        $response->assertSessionHasErrors('error');
        $this->assertDatabaseHas('spaces', ['id' => $space->id]);
    }

    public function test_superadmin_can_manage_spaces_across_venues(): void
    {
        $space = Space::factory()->create([
            'venue_id' => $this->venueB->id,
            'name' => 'Space Milik Owner B',
            'slug' => 'space-milik-owner-b',
        ]);

        $response = $this->actingAs($this->superadmin)->get(
            route('owner.venues.spaces.index', $this->venueB)
        );
        $response->assertOk();

        $updateResponse = $this->actingAs($this->superadmin)->put(
            route('owner.venues.spaces.update', [$this->venueB, $space]),
            [
                'name' => 'Space Diupdate Superadmin',
                'category' => 'studio',
                'capacity' => 15,
                'base_price' => 300000,
                'instant_book' => true,
                'cancellation_policy' => 'free_cancellation',
                'cancellation_cutoff_hours' => 6,
                'refund_percentage' => 100,
                'is_active' => true,
            ]
        );
        $updateResponse->assertRedirect(route('owner.venues.spaces.index', $this->venueB));

        $this->assertDatabaseHas('spaces', [
            'id' => $space->id,
            'name' => 'Space Diupdate Superadmin',
            'slug' => 'space-diupdate-superadmin',
        ]);
    }
}
