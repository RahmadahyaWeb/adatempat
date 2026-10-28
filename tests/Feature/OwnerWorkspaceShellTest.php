<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerWorkspaceShellTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_owner_routes_use_dedicated_owner_workspace_shell(): void
    {
        $owner = User::factory()->create([
            'role' => User::ROLE_OWNER,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($owner)
            ->get(route('owner.businesses.index'))
            ->assertOk()
            ->assertSee('Adatempat Owner', false)
            ->assertSee('Usaha & venue')
            ->assertSee('Booking')
            ->assertSee('Langganan')
            ->assertSee('Rekening payout')
            ->assertSee('Lihat marketplace')
            ->assertDontSee('Temukan tempat yang tepat untuk kebutuhanmu.');
    }

    public function test_owner_business_context_only_uses_business_owned_by_authenticated_owner(): void
    {
        $owner = User::factory()->create([
            'role' => User::ROLE_OWNER,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $otherOwner = User::factory()->create([
            'role' => User::ROLE_OWNER,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $ownedBusiness = $owner->businesses()->create([
            'name' => 'Usaha Milik Saya',
            'slug' => 'usaha-milik-saya',
            'status' => Business::STATUS_DRAFT,
        ]);

        $otherOwner->businesses()->create([
            'name' => 'Usaha Owner Lain',
            'slug' => 'usaha-owner-lain',
            'status' => Business::STATUS_DRAFT,
        ]);

        $this->actingAs($owner)
            ->get(route('owner.businesses.venues.index', ['business' => $ownedBusiness->public_id]))
            ->assertOk()
            ->assertSee('Usaha aktif')
            ->assertSee('Usaha Milik Saya')
            ->assertDontSee('Usaha Owner Lain');
    }

    public function test_owner_mfa_routes_do_not_use_workspace_shell(): void
    {
        $owner = User::factory()->create([
            'role' => User::ROLE_OWNER,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($owner)
            ->get(route('owner.mfa.setup'))
            ->assertOk()
            ->assertDontSee('owner-shell-v2-sidebar', false);
    }
}
