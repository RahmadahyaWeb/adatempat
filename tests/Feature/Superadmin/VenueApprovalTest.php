<?php

namespace Tests\Feature\Superadmin;

use App\Enums\UserRole;
use App\Enums\VenueApprovalStatus;
use App\Models\Business;
use App\Models\Space;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class VenueApprovalTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;

    private User $owner;

    private Business $business;

    private Venue $venue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superadmin = User::factory()->create([
            'role' => UserRole::Superadmin,
            'name' => 'Superadmin Admin',
            'email' => 'superadmin@adatempat.test',
            'two_factor_secret' => 'dummy-secret',
            'two_factor_confirmed_at' => now(),
        ]);

        $this->owner = User::factory()->create([
            'role' => UserRole::Owner,
            'name' => 'Owner Budi',
            'email' => 'owner.budi@adatempat.test',
        ]);

        $this->business = Business::factory()->create([
            'user_id' => $this->owner->id,
            'name' => 'Budi Sports Arena',
        ]);

        $this->venue = Venue::factory()->create([
            'business_id' => $this->business->id,
            'name' => 'GOR Bulutangkis Budi',
            'slug' => 'gor-bulutangkis-budi',
            'city' => 'Jakarta Selatan',
            'approval_status' => VenueApprovalStatus::Pending,
            'is_published' => false,
        ]);

        Space::factory()->create([
            'venue_id' => $this->venue->id,
            'name' => 'Lapangan 1',
        ]);
    }

    public function test_superadmin_can_view_venue_approval_queue(): void
    {
        $response = $this->actingAs($this->superadmin)
            ->get(route('superadmin.venues.index'));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('superadmin/venues/index')
                ->has('venues.data', 1)
                ->where('venues.data.0.name', 'GOR Bulutangkis Budi')
                ->where('stats.pending_count', 1),
            );
    }

    public function test_superadmin_can_view_detailed_venue_submission(): void
    {
        $response = $this->actingAs($this->superadmin)
            ->get(route('superadmin.venues.show', $this->venue));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('superadmin/venues/show')
                ->where('venue.name', 'GOR Bulutangkis Budi')
                ->has('venue.spaces', 1)
                ->where('venue.business.name', 'Budi Sports Arena'),
            );
    }

    public function test_superadmin_can_approve_venue_and_records_audit_log(): void
    {
        $response = $this->actingAs($this->superadmin)
            ->post(route('superadmin.venues.approve', $this->venue));

        $response->assertSessionHas('success');

        $this->venue->refresh();
        $this->assertSame(VenueApprovalStatus::Approved, $this->venue->approval_status);
        $this->assertNotNull($this->venue->approved_at);
        $this->assertSame($this->superadmin->id, $this->venue->approved_by);
        $this->assertNull($this->venue->rejection_reason);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->superadmin->id,
            'action' => 'venue.approved',
            'auditable_type' => Venue::class,
            'auditable_id' => $this->venue->id,
        ]);
    }

    public function test_approved_venue_can_be_published_by_owner(): void
    {
        $this->venue->update([
            'approval_status' => VenueApprovalStatus::Approved,
            'approved_at' => now(),
            'approved_by' => $this->superadmin->id,
        ]);

        $response = $this->actingAs($this->owner)
            ->post(route('owner.venues.publish', $this->venue));

        $response->assertSessionHas('success');

        $this->venue->refresh();
        $this->assertTrue($this->venue->is_published);
    }

    public function test_superadmin_can_reject_venue_with_reason_and_records_audit_log(): void
    {
        $response = $this->actingAs($this->superadmin)
            ->post(route('superadmin.venues.reject', $this->venue), [
                'rejection_reason' => 'Foto pencahayaan lapangan kurang memadai dan nomor kontak tidak aktif.',
            ]);

        $response->assertSessionHas('success');

        $this->venue->refresh();
        $this->assertSame(VenueApprovalStatus::Rejected, $this->venue->approval_status);
        $this->assertSame('Foto pencahayaan lapangan kurang memadai dan nomor kontak tidak aktif.', $this->venue->rejection_reason);
        $this->assertFalse($this->venue->is_published);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->superadmin->id,
            'action' => 'venue.rejected',
            'auditable_type' => Venue::class,
            'auditable_id' => $this->venue->id,
        ]);
    }

    public function test_reject_venue_requires_rejection_reason(): void
    {
        $response = $this->actingAs($this->superadmin)
            ->post(route('superadmin.venues.reject', $this->venue), [
                'rejection_reason' => '',
            ]);

        $response->assertSessionHasErrors('rejection_reason');

        $this->venue->refresh();
        $this->assertSame(VenueApprovalStatus::Pending, $this->venue->approval_status);
    }

    public function test_rejected_venue_cannot_be_published_and_remains_hidden(): void
    {
        $this->venue->update([
            'approval_status' => VenueApprovalStatus::Rejected,
            'rejection_reason' => 'Alasan penolakan',
            'is_published' => false,
        ]);

        $response = $this->actingAs($this->owner)
            ->post(route('owner.venues.publish', $this->venue));

        $response->assertSessionHas('error');

        $this->venue->refresh();
        $this->assertFalse($this->venue->is_published);
    }

    public function test_superadmin_can_unpublish_venue_with_reason_and_records_audit_log(): void
    {
        $this->venue->update([
            'approval_status' => VenueApprovalStatus::Approved,
            'is_published' => true,
        ]);

        $response = $this->actingAs($this->superadmin)
            ->post(route('superadmin.venues.unpublish', $this->venue), [
                'reason' => 'Laporan pengguna bahwa lapangan sedang renovasi darurat.',
            ]);

        $response->assertSessionHas('success');

        $this->venue->refresh();
        $this->assertFalse($this->venue->is_published);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->superadmin->id,
            'action' => 'venue.unpublished',
            'auditable_type' => Venue::class,
            'auditable_id' => $this->venue->id,
        ]);
    }

    public function test_owner_cannot_access_superadmin_approval_endpoints_forbidden_403(): void
    {
        $this->actingAs($this->owner)
            ->get(route('superadmin.venues.index'))
            ->assertForbidden();

        $this->actingAs($this->owner)
            ->get(route('superadmin.venues.show', $this->venue))
            ->assertForbidden();

        $this->actingAs($this->owner)
            ->post(route('superadmin.venues.approve', $this->venue))
            ->assertForbidden();

        $this->actingAs($this->owner)
            ->post(route('superadmin.venues.reject', $this->venue), ['rejection_reason' => 'Reason'])
            ->assertForbidden();
    }

    public function test_customer_cannot_access_superadmin_approval_endpoints_forbidden_403(): void
    {
        $customer = User::factory()->create([
            'role' => UserRole::Customer,
        ]);

        $this->actingAs($customer)
            ->get(route('superadmin.venues.index'))
            ->assertForbidden();

        $this->actingAs($customer)
            ->post(route('superadmin.venues.approve', $this->venue))
            ->assertForbidden();
    }
}
