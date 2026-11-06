<?php

namespace Tests\Feature\Auth;

use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_only_lists_own_businesses(): void
    {
        $owner = User::factory()->owner()->create();
        $ownBusiness = Business::factory()->create(['user_id' => $owner->id]);
        Business::factory()->create();

        $this->actingAs($owner)
            ->getJson(route('owner.businesses.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownBusiness->id);
    }

    public function test_owner_cannot_view_another_owners_business(): void
    {
        $owner = User::factory()->owner()->create();
        $otherBusiness = Business::factory()->create();

        $this->actingAs($owner)
            ->getJson(route('owner.businesses.show', $otherBusiness))
            ->assertForbidden();
    }

    public function test_superadmin_can_view_any_business(): void
    {
        $superadmin = User::factory()->superadmin()->withTwoFactor()->create();
        $business = Business::factory()->create();

        $this->actingAs($superadmin)
            ->getJson(route('superadmin.businesses.show', $business))
            ->assertOk()
            ->assertJsonPath('data.id', $business->id);
    }

    public function test_owner_cannot_access_superadmin_routes(): void
    {
        $owner = User::factory()->owner()->create();
        $business = Business::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($owner)
            ->getJson(route('superadmin.businesses.show', $business))
            ->assertForbidden();
    }

    public function test_customer_cannot_access_owner_routes(): void
    {
        $customer = User::factory()->customer()->create();

        $this->actingAs($customer)
            ->getJson(route('owner.businesses.index'))
            ->assertForbidden();
    }

    public function test_guests_are_redirected_from_owner_routes(): void
    {
        $business = Business::factory()->create();

        $this->get(route('owner.businesses.show', $business))
            ->assertRedirect(route('login'));
    }
}
