<?php

namespace Tests\Feature\Owner;

use App\Enums\UserRole;
use App\Enums\VenueApprovalStatus;
use App\Models\Business;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OwnerVenueTest extends TestCase
{
    use RefreshDatabase;

    private User $ownerA;

    private Business $businessA;

    private User $ownerB;

    private Business $businessB;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->ownerA = User::factory()->create([
            'role' => UserRole::Owner,
            'name' => 'Owner Alpha',
            'email' => 'owner.a@adatempat.test',
        ]);

        $this->businessA = Business::factory()->create([
            'user_id' => $this->ownerA->id,
            'name' => 'Alpha Sports Center',
        ]);

        $this->ownerB = User::factory()->create([
            'role' => UserRole::Owner,
            'name' => 'Owner Beta',
            'email' => 'owner.b@adatempat.test',
        ]);

        $this->businessB = Business::factory()->create([
            'user_id' => $this->ownerB->id,
            'name' => 'Beta Arena',
        ]);
    }

    public function test_owner_only_lists_own_venues(): void
    {
        $venueA = Venue::factory()->create([
            'business_id' => $this->businessA->id,
            'name' => 'Alpha Futsal Arena',
        ]);

        $venueB = Venue::factory()->create([
            'business_id' => $this->businessB->id,
            'name' => 'Beta Badminton Club',
        ]);

        $this->actingAs($this->ownerA)
            ->get(route('owner.venues.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('owner/venues/index')
                ->has('venues.data', 1)
                ->where('venues.data.0.id', $venueA->id)
                ->where('venues.data.0.name', 'Alpha Futsal Arena')
            );
    }

    public function test_owner_cannot_view_or_edit_another_owners_venue(): void
    {
        $venueB = Venue::factory()->create([
            'business_id' => $this->businessB->id,
            'name' => 'Beta Arena',
        ]);

        $this->actingAs($this->ownerA)
            ->get(route('owner.venues.edit', $venueB))
            ->assertForbidden();

        $this->actingAs($this->ownerA)
            ->put(route('owner.venues.update', $venueB), [
                'name' => 'Hacked Beta Arena',
                'city' => 'Jakarta',
                'address' => 'Jl. Ilegal No. 1',
                'timezone' => 'Asia/Jakarta',
            ])
            ->assertForbidden();

        $this->actingAs($this->ownerA)
            ->delete(route('owner.venues.destroy', $venueB))
            ->assertForbidden();

        $this->actingAs($this->ownerA)
            ->post(route('owner.venues.publish', $venueB))
            ->assertForbidden();
    }

    public function test_customer_cannot_access_owner_venues(): void
    {
        $customer = User::factory()->create(['role' => UserRole::Customer]);

        $this->actingAs($customer)
            ->get(route('owner.venues.index'))
            ->assertForbidden();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('owner.venues.index'))
            ->assertRedirect(route('login'));
    }

    public function test_superadmin_can_view_all_venues(): void
    {
        $superadmin = User::factory()->create(['role' => UserRole::Superadmin]);

        Venue::factory()->create(['business_id' => $this->businessA->id, 'name' => 'Alpha Venue']);
        Venue::factory()->create(['business_id' => $this->businessB->id, 'name' => 'Beta Venue']);

        $this->actingAs($superadmin)
            ->get(route('owner.venues.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('owner/venues/index')
                ->has('venues.data', 2)
            );
    }

    public function test_owner_can_create_venue_with_private_cover_photo(): void
    {
        $file = UploadedFile::fake()->image('cover-futsal.jpg', 1200, 800);

        $response = $this->actingAs($this->ownerA)
            ->post(route('owner.venues.store'), [
                'business_id' => $this->businessA->id,
                'name' => 'Alpha Stadium Samarinda',
                'city' => 'Samarinda',
                'address' => 'Jl. Pahlawan No. 45',
                'timezone' => 'Asia/Makassar',
                'contact_phone' => '081234567890',
                'cover_photo' => $file,
            ]);

        $response->assertRedirect(route('owner.venues.index'));
        $response->assertSessionHas('success');

        $venue = Venue::where('name', 'Alpha Stadium Samarinda')->first();
        $this->assertNotNull($venue);
        $this->assertEquals('alpha-stadium-samarinda', $venue->slug);
        $this->assertEquals(VenueApprovalStatus::Pending, $venue->approval_status);
        $this->assertFalse($venue->is_published);
        $this->assertEquals('Asia/Makassar', $venue->timezone);
        $this->assertNotNull($venue->cover_photo_path);

        Storage::disk('local')->assertExists($venue->cover_photo_path);
    }

    public function test_private_cover_requires_valid_signature_or_authorized_user(): void
    {
        $coverPath = UploadedFile::fake()->image('venue-cover.jpg')->store('venues/covers', 'local');

        $venue = Venue::factory()->create([
            'business_id' => $this->businessA->id,
            'cover_photo_path' => $coverPath,
        ]);

        // 1. Guest without signature -> 403
        $this->get(route('venues.cover', $venue))
            ->assertForbidden();

        // 2. Guest with valid signed URL -> 200 stream
        $signedUrl = $venue->cover_url;
        $this->assertNotNull($signedUrl);

        $this->get($signedUrl)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');

        // 3. Guest with tampered signature -> 403
        $tamperedUrl = $signedUrl.'&extra=tampered';
        $this->get($tamperedUrl)
            ->assertForbidden();

        // 4. Authenticated owner of the venue without signature -> 200
        $this->actingAs($this->ownerA)
            ->get(route('venues.cover', $venue))
            ->assertOk();

        // 5. Other owner without signature -> 403
        $this->actingAs($this->ownerB)
            ->get(route('venues.cover', $venue))
            ->assertForbidden();
    }

    public function test_owner_cannot_publish_pending_or_rejected_venue(): void
    {
        $pendingVenue = Venue::factory()->create([
            'business_id' => $this->businessA->id,
            'approval_status' => VenueApprovalStatus::Pending,
            'is_published' => false,
        ]);

        $this->actingAs($this->ownerA)
            ->post(route('owner.venues.publish', $pendingVenue))
            ->assertSessionHas('error');

        $this->assertFalse($pendingVenue->fresh()->is_published);

        $rejectedVenue = Venue::factory()->rejected()->create([
            'business_id' => $this->businessA->id,
        ]);

        $this->actingAs($this->ownerA)
            ->post(route('owner.venues.publish', $rejectedVenue))
            ->assertSessionHas('error');

        $this->assertFalse($rejectedVenue->fresh()->is_published);
    }

    public function test_owner_can_toggle_publish_for_approved_venue(): void
    {
        $approvedVenue = Venue::factory()->approved()->create([
            'business_id' => $this->businessA->id,
            'is_published' => true,
        ]);

        // Toggle to unpublish
        $this->actingAs($this->ownerA)
            ->post(route('owner.venues.publish', $approvedVenue))
            ->assertSessionHas('success');

        $this->assertFalse($approvedVenue->fresh()->is_published);

        // Toggle back to publish
        $this->actingAs($this->ownerA)
            ->post(route('owner.venues.publish', $approvedVenue))
            ->assertSessionHas('success');

        $this->assertTrue($approvedVenue->fresh()->is_published);
    }

    public function test_material_change_reverts_approved_venue_to_pending_and_unpublishes(): void
    {
        $venue = Venue::factory()->approved()->create([
            'business_id' => $this->businessA->id,
            'name' => 'Original Approved Name',
            'address' => 'Jl. Asli No. 10',
            'is_published' => true,
        ]);

        $this->actingAs($this->ownerA)
            ->put(route('owner.venues.update', $venue), [
                'name' => 'New Name Requiring Review',
                'city' => $venue->city,
                'address' => $venue->address,
                'timezone' => $venue->timezone,
            ])
            ->assertRedirect(route('owner.venues.index'))
            ->assertSessionHas('info');

        $fresh = $venue->fresh();
        $this->assertEquals('New Name Requiring Review', $fresh->name);
        $this->assertEquals('new-name-requiring-review', $fresh->slug);
        $this->assertEquals(VenueApprovalStatus::Pending, $fresh->approval_status);
        $this->assertFalse($fresh->is_published);
        $this->assertNull($fresh->approved_at);
    }

    public function test_non_material_change_keeps_approved_status(): void
    {
        $venue = Venue::factory()->approved()->create([
            'business_id' => $this->businessA->id,
            'name' => 'Original Name',
            'address' => 'Jl. Asli No. 10',
            'is_published' => true,
        ]);

        $this->actingAs($this->ownerA)
            ->put(route('owner.venues.update', $venue), [
                'name' => 'Original Name',
                'city' => $venue->city,
                'address' => 'Jl. Asli No. 10',
                'timezone' => 'Asia/Jayapura',
                'contact_phone' => '08987654321',
            ])
            ->assertRedirect(route('owner.venues.index'))
            ->assertSessionHas('success');

        $fresh = $venue->fresh();
        $this->assertEquals(VenueApprovalStatus::Approved, $fresh->approval_status);
        $this->assertTrue($fresh->is_published);
        $this->assertEquals('Asia/Jayapura', $fresh->timezone);
        $this->assertEquals('08987654321', $fresh->contact_phone);
    }

    public function test_owner_can_submit_rejected_venue_for_reapproval(): void
    {
        $venue = Venue::factory()->rejected('Foto cover buram')->create([
            'business_id' => $this->businessA->id,
        ]);

        $this->actingAs($this->ownerA)
            ->post(route('owner.venues.submit-approval', $venue))
            ->assertSessionHas('success');

        $fresh = $venue->fresh();
        $this->assertEquals(VenueApprovalStatus::Pending, $fresh->approval_status);
        $this->assertNull($fresh->rejection_reason);
    }

    public function test_pending_or_unapproved_venue_does_not_appear_on_public_marketplace(): void
    {
        $pendingVenue = Venue::factory()->create([
            'business_id' => $this->businessA->id,
            'approval_status' => VenueApprovalStatus::Pending,
            'is_published' => false,
        ]);

        // Public show should 404
        $this->get(route('venues.show', $pendingVenue))
            ->assertNotFound();

        // Public home should not include it
        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee($pendingVenue->name);
    }
}
