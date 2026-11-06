<?php

namespace Tests\Feature\Owner;

use App\Enums\AddOnPricingType;
use App\Enums\SpaceCategory;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Business;
use App\Models\Space;
use App\Models\SpaceAddOn;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OwnerAddOnTest extends TestCase
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
            'name' => 'Alpha Sports Center',
        ]);

        $this->venueA = Venue::factory()->create([
            'business_id' => $this->businessA->id,
            'name' => 'Alpha Arena',
            'slug' => 'alpha-arena',
        ]);

        $this->spaceA = Space::factory()->create([
            'venue_id' => $this->venueA->id,
            'name' => 'Alpha Futsal Court',
            'slug' => 'alpha-futsal-court',
            'category' => SpaceCategory::Futsal,
            'base_price' => 150000,
        ]);

        $this->ownerB = User::factory()->create([
            'role' => UserRole::Owner,
            'email' => 'owner.b@adatempat.test',
        ]);

        $this->businessB = Business::factory()->create([
            'user_id' => $this->ownerB->id,
            'name' => 'Beta Sports Hub',
        ]);

        $this->venueB = Venue::factory()->create([
            'business_id' => $this->businessB->id,
            'name' => 'Beta Arena',
            'slug' => 'beta-arena',
        ]);

        $this->spaceB = Space::factory()->create([
            'venue_id' => $this->venueB->id,
            'name' => 'Beta Badminton Court',
            'slug' => 'beta-badminton-court',
        ]);
    }

    public function test_guest_cannot_manage_addons(): void
    {
        $response = $this->get(route('owner.venues.spaces.add-ons.index', [$this->venueA, $this->spaceA]));
        $response->assertRedirect(route('login'));

        $postResponse = $this->post(route('owner.venues.spaces.add-ons.store', [$this->venueA, $this->spaceA]), []);
        $postResponse->assertRedirect(route('login'));
    }

    public function test_owner_can_view_space_addons(): void
    {
        SpaceAddOn::factory()->create([
            'space_id' => $this->spaceA->id,
            'name' => 'Sewa Rompi Tim',
            'price' => 25000,
            'pricing_type' => AddOnPricingType::PerHour,
            'stock_quantity' => 4,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->ownerA)->get(
            route('owner.venues.spaces.add-ons.index', [$this->venueA, $this->spaceA])
        );

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('owner/spaces/add-ons')
            ->has('venue')
            ->has('space')
            ->has('addOns', 1)
            ->where('addOns.0.name', 'Sewa Rompi Tim')
            ->where('addOns.0.price', 25000)
            ->where('addOns.0.pricing_type', 'per_hour')
        );
    }

    public function test_owner_cannot_view_addons_of_other_owner_space(): void
    {
        $response = $this->actingAs($this->ownerA)->get(
            route('owner.venues.spaces.add-ons.index', [$this->venueB, $this->spaceB])
        );
        $response->assertForbidden();
    }

    public function test_owner_can_create_addon_with_different_pricing_types(): void
    {
        $payloadPerHour = [
            'name' => 'Jasa Wasit Futsal',
            'price' => 75000,
            'pricing_type' => 'per_hour',
            'stock_quantity' => 2,
            'is_active' => true,
        ];

        $response = $this->actingAs($this->ownerA)->post(
            route('owner.venues.spaces.add-ons.store', [$this->venueA, $this->spaceA]),
            $payloadPerHour
        );
        $response->assertRedirect(route('owner.venues.spaces.add-ons.index', [$this->venueA, $this->spaceA]));

        $this->assertDatabaseHas('space_add_ons', [
            'space_id' => $this->spaceA->id,
            'name' => 'Jasa Wasit Futsal',
            'price' => 75000,
            'pricing_type' => 'per_hour',
            'stock_quantity' => 2,
        ]);

        $payloadPerItem = [
            'name' => 'Sewa Sepatu Futsal',
            'price' => 30000,
            'pricing_type' => 'per_item',
            'stock_quantity' => 10,
            'is_active' => true,
        ];

        $this->actingAs($this->ownerA)->post(
            route('owner.venues.spaces.add-ons.store', [$this->venueA, $this->spaceA]),
            $payloadPerItem
        );

        $this->assertDatabaseHas('space_add_ons', [
            'space_id' => $this->spaceA->id,
            'name' => 'Sewa Sepatu Futsal',
            'pricing_type' => 'per_item',
        ]);
    }

    public function test_store_addon_validation_errors(): void
    {
        $response = $this->actingAs($this->ownerA)->post(
            route('owner.venues.spaces.add-ons.store', [$this->venueA, $this->spaceA]),
            []
        );

        $response->assertSessionHasErrors(['name', 'price', 'pricing_type', 'stock_quantity']);
    }

    public function test_owner_can_update_addon(): void
    {
        $addOn = SpaceAddOn::factory()->create([
            'space_id' => $this->spaceA->id,
            'name' => 'Bola Futsal Standar',
            'price' => 15000,
            'pricing_type' => AddOnPricingType::PerBooking,
            'stock_quantity' => 5,
            'is_active' => true,
        ]);

        $payload = [
            'name' => 'Bola Futsal FIFA Quality Pro',
            'price' => 25000,
            'pricing_type' => 'per_booking',
            'stock_quantity' => 8,
            'is_active' => false,
        ];

        $response = $this->actingAs($this->ownerA)->put(
            route('owner.venues.spaces.add-ons.update', [$this->venueA, $this->spaceA, $addOn]),
            $payload
        );

        $response->assertRedirect(route('owner.venues.spaces.add-ons.index', [$this->venueA, $this->spaceA]));

        $this->assertDatabaseHas('space_add_ons', [
            'id' => $addOn->id,
            'name' => 'Bola Futsal FIFA Quality Pro',
            'price' => 25000,
            'stock_quantity' => 8,
            'is_active' => false,
        ]);
    }

    public function test_owner_can_delete_addon_without_bookings(): void
    {
        $addOn = SpaceAddOn::factory()->create([
            'space_id' => $this->spaceA->id,
            'name' => 'Addon Belum Terpakai',
        ]);

        $response = $this->actingAs($this->ownerA)->delete(
            route('owner.venues.spaces.add-ons.destroy', [$this->venueA, $this->spaceA, $addOn])
        );

        $response->assertRedirect(route('owner.venues.spaces.add-ons.index', [$this->venueA, $this->spaceA]));
        $this->assertDatabaseMissing('space_add_ons', ['id' => $addOn->id]);
    }

    public function test_owner_cannot_delete_addon_with_booking_history(): void
    {
        $addOn = SpaceAddOn::factory()->create([
            'space_id' => $this->spaceA->id,
            'name' => 'Addon Pernah Dipesan',
            'price' => 20000,
        ]);

        $customer = User::factory()->create();
        $booking = Booking::factory()->create([
            'venue_id' => $this->venueA->id,
            'space_id' => $this->spaceA->id,
            'user_id' => $customer->id,
        ]);

        DB::table('booking_add_ons')->insert([
            'booking_id' => $booking->id,
            'space_add_on_id' => $addOn->id,
            'name' => $addOn->name,
            'unit_price' => $addOn->price,
            'quantity' => 1,
            'total_price' => $addOn->price,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->ownerA)->delete(
            route('owner.venues.spaces.add-ons.destroy', [$this->venueA, $this->spaceA, $addOn])
        );

        $response->assertSessionHasErrors('error');
        $this->assertDatabaseHas('space_add_ons', ['id' => $addOn->id]);
    }

    public function test_cross_venue_addon_access_prevention(): void
    {
        $addOnB = SpaceAddOn::factory()->create([
            'space_id' => $this->spaceB->id,
            'name' => 'Addon Milik Venue B',
        ]);

        // Attempting to delete Addon B under Venue A / Space A -> 404
        $response = $this->actingAs($this->ownerA)->delete(
            route('owner.venues.spaces.add-ons.destroy', [$this->venueA, $this->spaceA, $addOnB])
        );
        $response->assertNotFound();
    }
}
