<?php

namespace Tests\Feature\Security;

use App\Enums\AddOnPricingType;
use App\Enums\BookingStatus;
use App\Enums\CancellationPolicy;
use App\Enums\SpaceCategory;
use App\Enums\UserRole;
use App\Enums\VenueApprovalStatus;
use App\Models\Booking;
use App\Models\Business;
use App\Models\Space;
use App\Models\SpaceAddOn;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TenantIsolationMatrixTest extends TestCase
{
    use RefreshDatabase;

    private User $ownerA;

    private Business $businessA;

    private Venue $venueA;

    private Space $spaceA;

    private SpaceAddOn $addOnA;

    private User $ownerB;

    private Business $businessB;

    private Venue $venueB;

    private Space $spaceB;

    private SpaceAddOn $addOnB;

    private User $customer;

    private User $superadminWith2fa;

    private User $superadminNo2fa;

    protected function setUp(): void
    {
        parent::setUp();

        // Tenant A
        $this->ownerA = User::factory()->create([
            'role' => UserRole::Owner,
            'name' => 'Owner Alpha',
            'email' => 'alpha@owner.test',
        ]);
        $this->businessA = Business::factory()->create([
            'user_id' => $this->ownerA->id,
            'name' => 'Alpha Sports Group',
        ]);
        $this->venueA = Venue::factory()->create([
            'business_id' => $this->businessA->id,
            'name' => 'Alpha Stadium',
            'slug' => 'alpha-stadium',
            'city' => 'Jakarta Selatan',
            'approval_status' => VenueApprovalStatus::Approved,
            'is_published' => true,
        ]);
        $this->spaceA = Space::factory()->create([
            'venue_id' => $this->venueA->id,
            'name' => 'Alpha Main Court',
            'slug' => 'alpha-main-court',
            'category' => SpaceCategory::Futsal,
            'base_price' => 150000,
            'instant_book' => true,
            'cancellation_policy' => CancellationPolicy::FreeCancellation,
            'refund_percentage' => 100,
            'is_active' => true,
        ]);
        $this->addOnA = SpaceAddOn::create([
            'space_id' => $this->spaceA->id,
            'name' => 'Alpha Ball Rental',
            'price' => 25000,
            'pricing_type' => AddOnPricingType::PerBooking,
            'stock_quantity' => 5,
            'is_active' => true,
        ]);

        // Tenant B (Attacker / Cross-tenant)
        $this->ownerB = User::factory()->create([
            'role' => UserRole::Owner,
            'name' => 'Owner Beta',
            'email' => 'beta@owner.test',
        ]);
        $this->businessB = Business::factory()->create([
            'user_id' => $this->ownerB->id,
            'name' => 'Beta Arena Corp',
        ]);
        $this->venueB = Venue::factory()->create([
            'business_id' => $this->businessB->id,
            'name' => 'Beta Court',
            'slug' => 'beta-court',
            'city' => 'Bandung',
            'approval_status' => VenueApprovalStatus::Approved,
            'is_published' => true,
        ]);
        $this->spaceB = Space::factory()->create([
            'venue_id' => $this->venueB->id,
            'name' => 'Beta Badminton 1',
            'slug' => 'beta-badminton-1',
            'category' => SpaceCategory::Badminton,
            'base_price' => 80000,
            'instant_book' => true,
            'cancellation_policy' => CancellationPolicy::FreeCancellation,
            'refund_percentage' => 100,
            'is_active' => true,
        ]);
        $this->addOnB = SpaceAddOn::create([
            'space_id' => $this->spaceB->id,
            'name' => 'Beta Racket',
            'price' => 15000,
            'pricing_type' => AddOnPricingType::PerHour,
            'stock_quantity' => 4,
            'is_active' => true,
        ]);

        // Customer & Superadmin
        $this->customer = User::factory()->create(['role' => UserRole::Customer]);
        $this->superadminWith2fa = User::factory()->create([
            'role' => UserRole::Superadmin,
            'two_factor_secret' => encrypt('secret'),
            'two_factor_confirmed_at' => now(),
        ]);
        $this->superadminNo2fa = User::factory()->create([
            'role' => UserRole::Superadmin,
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | 1. Venue Tenant Isolation & IDOR Protection
    |--------------------------------------------------------------------------
    */

    public function test_owner_b_cannot_view_or_edit_owner_a_venue(): void
    {
        $this->actingAs($this->ownerB)
            ->get(route('owner.venues.edit', $this->venueA))
            ->assertForbidden();

        $this->actingAs($this->customer)
            ->get(route('owner.venues.edit', $this->venueA))
            ->assertForbidden();

        auth()->logout();
        $this->get(route('owner.venues.edit', $this->venueA))
            ->assertRedirect(route('login'));
    }

    public function test_owner_b_cannot_update_or_delete_owner_a_venue(): void
    {
        $this->actingAs($this->ownerB)
            ->put(route('owner.venues.update', $this->venueA), [
                'name' => 'Hacked Venue Name',
                'city' => 'Jakarta Selatan',
                'address' => 'Jl. Baru No 1',
                'timezone' => 'Asia/Jakarta',
            ])
            ->assertForbidden();

        $this->venueA->refresh();
        $this->assertSame('Alpha Stadium', $this->venueA->name);

        $this->actingAs($this->ownerB)
            ->delete(route('owner.venues.destroy', $this->venueA))
            ->assertForbidden();

        $this->assertDatabaseHas('venues', ['id' => $this->venueA->id]);
    }

    public function test_owner_b_cannot_toggle_publish_or_submit_approval_for_owner_a_venue(): void
    {
        $this->actingAs($this->ownerB)
            ->post(route('owner.venues.publish', $this->venueA))
            ->assertForbidden();

        $this->actingAs($this->ownerB)
            ->post(route('owner.venues.submit-approval', $this->venueA))
            ->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | 2. Space Tenant Isolation & IDOR Protection
    |--------------------------------------------------------------------------
    */

    public function test_owner_b_cannot_view_or_create_spaces_in_owner_a_venue(): void
    {
        $this->actingAs($this->ownerB)
            ->get(route('owner.venues.spaces.index', $this->venueA))
            ->assertForbidden();

        $this->actingAs($this->ownerB)
            ->get(route('owner.venues.spaces.create', $this->venueA))
            ->assertForbidden();

        $this->actingAs($this->ownerB)
            ->post(route('owner.venues.spaces.store', $this->venueA), [
                'name' => 'Illegal Space',
                'category' => SpaceCategory::Futsal->value,
                'capacity' => 10,
                'base_price' => 100000,
                'instant_book' => true,
                'cancellation_policy' => CancellationPolicy::FreeCancellation->value,
                'cancellation_cutoff_hours' => 24,
                'refund_percentage' => 100,
            ])
            ->assertForbidden();
    }

    public function test_owner_b_cannot_update_or_delete_owner_a_space(): void
    {
        $this->actingAs($this->ownerB)
            ->get(route('owner.venues.spaces.edit', [$this->venueA, $this->spaceA]))
            ->assertForbidden();

        $this->actingAs($this->ownerB)
            ->put(route('owner.venues.spaces.update', [$this->venueA, $this->spaceA]), [
                'name' => 'Hijacked Space',
                'category' => SpaceCategory::Futsal->value,
                'capacity' => 12,
                'base_price' => 200000,
                'instant_book' => true,
                'cancellation_policy' => CancellationPolicy::FreeCancellation->value,
                'cancellation_cutoff_hours' => 24,
                'refund_percentage' => 100,
                'is_active' => true,
            ])
            ->assertForbidden();

        $this->spaceA->refresh();
        $this->assertSame('Alpha Main Court', $this->spaceA->name);

        $this->actingAs($this->ownerB)
            ->delete(route('owner.venues.spaces.destroy', [$this->venueA, $this->spaceA]))
            ->assertForbidden();

        $this->assertDatabaseHas('spaces', ['id' => $this->spaceA->id]);
    }

    public function test_space_and_venue_url_mismatch_returns_404(): void
    {
        // Owner A tries to access Space A under Venue B URL
        $this->actingAs($this->ownerA)
            ->get(route('owner.venues.spaces.edit', [$this->venueB, $this->spaceA]))
            ->assertNotFound();

        $this->actingAs($this->ownerA)
            ->put(route('owner.venues.spaces.update', [$this->venueB, $this->spaceA]), [
                'name' => 'Mismatch Space',
                'category' => SpaceCategory::Futsal->value,
                'capacity' => 10,
                'base_price' => 100000,
                'instant_book' => true,
                'cancellation_policy' => CancellationPolicy::FreeCancellation->value,
                'cancellation_cutoff_hours' => 24,
                'refund_percentage' => 100,
                'is_active' => true,
            ])
            ->assertNotFound();
    }

    /*
    |--------------------------------------------------------------------------
    | 3. Schedule & Versioning Tenant Isolation
    |--------------------------------------------------------------------------
    */

    public function test_owner_b_cannot_view_or_modify_owner_a_schedules(): void
    {
        $this->actingAs($this->ownerB)
            ->get(route('owner.venues.spaces.schedules.index', [$this->venueA, $this->spaceA]))
            ->assertForbidden();

        $this->actingAs($this->ownerB)
            ->put(route('owner.venues.spaces.schedules.update', [$this->venueA, $this->spaceA]), [
                'schedules' => [
                    [
                        'day_of_week' => 1,
                        'open_time' => '07:00',
                        'close_time' => '23:00',
                        'slot_duration_minutes' => 60,
                        'is_active' => true,
                    ],
                ],
            ])
            ->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | 4. Add-On Tenant Isolation
    |--------------------------------------------------------------------------
    */

    public function test_owner_b_cannot_manage_owner_a_add_ons(): void
    {
        $this->actingAs($this->ownerB)
            ->get(route('owner.venues.spaces.add-ons.index', [$this->venueA, $this->spaceA]))
            ->assertForbidden();

        $this->actingAs($this->ownerB)
            ->post(route('owner.venues.spaces.add-ons.store', [$this->venueA, $this->spaceA]), [
                'name' => 'Illegal AddOn',
                'price' => 50000,
                'pricing_type' => AddOnPricingType::PerBooking->value,
                'stock_quantity' => 1,
            ])
            ->assertForbidden();

        $this->actingAs($this->ownerB)
            ->put(route('owner.venues.spaces.add-ons.update', [$this->venueA, $this->spaceA, $this->addOnA]), [
                'name' => 'Hacked AddOn',
                'price' => 10000,
                'pricing_type' => AddOnPricingType::PerBooking->value,
                'stock_quantity' => 1,
                'is_active' => true,
            ])
            ->assertForbidden();

        $this->actingAs($this->ownerB)
            ->delete(route('owner.venues.spaces.add-ons.destroy', [$this->venueA, $this->spaceA, $this->addOnA]))
            ->assertForbidden();

        $this->assertDatabaseHas('space_add_ons', ['id' => $this->addOnA->id]);
    }

    public function test_add_on_space_mismatch_returns_404(): void
    {
        // Try updating AddOn A under Space B
        $this->actingAs($this->ownerA)
            ->put(route('owner.venues.spaces.add-ons.update', [$this->venueA, $this->spaceB, $this->addOnA]), [
                'name' => 'Mismatch AddOn',
                'price' => 20000,
                'pricing_type' => AddOnPricingType::PerBooking->value,
                'stock_quantity' => 1,
                'is_active' => true,
            ])
            ->assertNotFound();
    }

    /*
    |--------------------------------------------------------------------------
    | 5. Superadmin Endpoint Protection & 2FA Guard
    |--------------------------------------------------------------------------
    */

    public function test_superadmin_endpoints_strictly_forbidden_for_non_superadmin(): void
    {
        foreach ([$this->ownerA, $this->customer] as $user) {
            $this->actingAs($user)
                ->get(route('superadmin.venues.index'))
                ->assertForbidden();

            $this->actingAs($user)
                ->get(route('superadmin.venues.show', $this->venueA))
                ->assertForbidden();

            $this->actingAs($user)
                ->post(route('superadmin.venues.approve', $this->venueA))
                ->assertForbidden();

            $this->actingAs($user)
                ->post(route('superadmin.venues.reject', $this->venueA), ['rejection_reason' => 'Reason'])
                ->assertForbidden();

            $this->actingAs($user)
                ->post(route('superadmin.venues.unpublish', $this->venueA), ['reason' => 'Reason'])
                ->assertForbidden();

            $this->actingAs($user)
                ->get(route('superadmin.audit.index'))
                ->assertForbidden();
        }
    }

    public function test_superadmin_without_two_factor_is_blocked_by_middleware(): void
    {
        $this->actingAs($this->superadminNo2fa)
            ->get(route('superadmin.venues.index'))
            ->assertRedirect(route('security.edit'));

        $this->actingAs($this->superadminNo2fa)
            ->getJson(route('superadmin.venues.index'))
            ->assertForbidden();

        $this->actingAs($this->superadminWith2fa)
            ->get(route('superadmin.venues.index'))
            ->assertOk();
    }

    /*
    |--------------------------------------------------------------------------
    | 6. Public Catalog Isolation (Pending & Rejected Venues)
    |--------------------------------------------------------------------------
    */

    public function test_pending_and_rejected_venues_are_completely_hidden_from_public_catalog(): void
    {
        $pendingVenue = Venue::factory()->create([
            'business_id' => $this->businessA->id,
            'name' => 'Hidden Pending Venue',
            'slug' => 'hidden-pending-venue',
            'approval_status' => VenueApprovalStatus::Pending,
            'is_published' => false,
        ]);
        $pendingSpace = Space::factory()->create([
            'venue_id' => $pendingVenue->id,
            'name' => 'Hidden Pending Court',
            'slug' => 'hidden-pending-court',
        ]);

        $rejectedVenue = Venue::factory()->create([
            'business_id' => $this->businessB->id,
            'name' => 'Hidden Rejected Venue',
            'slug' => 'hidden-rejected-venue',
            'approval_status' => VenueApprovalStatus::Rejected,
            'is_published' => false,
        ]);
        $rejectedSpace = Space::factory()->create([
            'venue_id' => $rejectedVenue->id,
            'name' => 'Hidden Rejected Court',
            'slug' => 'hidden-rejected-court',
        ]);

        // Public browse catalog (/spaces)
        $browseResponse = $this->get(route('spaces.index'));
        $browseResponse->assertOk()
            ->assertDontSee('Hidden Pending Court')
            ->assertDontSee('Hidden Rejected Court');

        // Direct public venue profile (/v/{slug})
        $this->get(route('venues.show', $pendingVenue))->assertNotFound();
        $this->get(route('venues.show', $rejectedVenue))->assertNotFound();

        // Direct public space slot picker (/spaces/{slug})
        $this->get(route('spaces.show', $pendingSpace))->assertNotFound();
        $this->get(route('spaces.show', $rejectedSpace))->assertNotFound();
    }

    /*
    |--------------------------------------------------------------------------
    | 7. Gate Scanner Cross-Tenant Isolation
    |--------------------------------------------------------------------------
    */

    public function test_owner_b_cannot_check_in_booking_for_owner_a_venue(): void
    {
        $booking = Booking::factory()->create([
            'venue_id' => $this->venueA->id,
            'space_id' => $this->spaceA->id,
            'user_id' => $this->customer->id,
            'status' => BookingStatus::Confirmed,
            'booking_date' => today(),
            'qr_checkin_token' => Str::random(64),
        ]);

        // Owner B tries to scan Customer's ticket for Venue A using Venue B scanner
        $response = $this->actingAs($this->ownerB)
            ->postJson(route('gate.scanner.scan'), [
                'venue_id' => $this->venueB->id,
                'token' => $booking->qr_checkin_token,
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => 'WRONG_VENUE',
            ]);

        $booking->refresh();
        $this->assertNull($booking->checked_in_at);
    }
}
