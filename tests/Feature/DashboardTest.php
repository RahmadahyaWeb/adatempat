<?php

namespace Tests\Feature;

use App\Enums\VenueApprovalStatus;
use App\Models\Business;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
    }

    public function test_owner_sees_business_and_venue_stats_on_dashboard()
    {
        $owner = User::factory()->owner()->create();
        $business = Business::factory()->create(['user_id' => $owner->id, 'name' => 'Mitra Futsal Jaya']);
        Venue::factory()->create([
            'business_id' => $business->id,
            'name' => 'Lapangan A',
            'approval_status' => VenueApprovalStatus::Approved,
            'is_published' => true,
        ]);

        $this->actingAs($owner);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('dashboard')
            ->has('ownerData')
            ->where('ownerData.business_name', 'Mitra Futsal Jaya')
            ->where('ownerData.stats.total_venues', 1)
            ->where('ownerData.stats.approved_count', 1)
            ->where('ownerData.stats.published_count', 1)
        );
    }

    public function test_superadmin_sees_platform_stats_and_pending_venues_on_dashboard()
    {
        $superadmin = User::factory()->superadmin()->create();
        $business = Business::factory()->create(['name' => 'Bisnis Mitra Test']);
        Venue::factory()->create([
            'business_id' => $business->id,
            'name' => 'Venue Menunggu',
            'approval_status' => VenueApprovalStatus::Pending,
        ]);

        $this->actingAs($superadmin);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('dashboard')
            ->has('superadminData')
            ->where('superadminData.stats.pending_venues_count', 1)
        );
    }
}
