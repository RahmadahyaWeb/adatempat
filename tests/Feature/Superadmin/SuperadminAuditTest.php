<?php

namespace Tests\Feature\Superadmin;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\Venue;
use App\Services\Audit\AuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SuperadminAuditTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superadmin = User::factory()->create([
            'role' => UserRole::Superadmin,
            'name' => 'Superadmin Audit Officer',
            'email' => 'superadmin.audit@adatempat.test',
            'two_factor_secret' => 'dummy-secret',
            'two_factor_confirmed_at' => now(),
        ]);
    }

    public function test_superadmin_can_view_audit_logs_with_pagination_and_filter(): void
    {
        AuditService::log('venue.approved', null, ['venue_name' => 'Venue A'], $this->superadmin);
        AuditService::log('venue.rejected', null, ['venue_name' => 'Venue B', 'reason' => 'Foto buram'], $this->superadmin);
        AuditService::log('user.2fa_enabled', $this->superadmin, ['email' => $this->superadmin->email], $this->superadmin);

        // View all
        $response = $this->actingAs($this->superadmin)
            ->get(route('superadmin.audit.index'));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('superadmin/audit/index')
                ->has('logs.data', 3)
                ->where('filters.category', 'all'),
            );

        // Filter by category venue
        $venueResponse = $this->actingAs($this->superadmin)
            ->get(route('superadmin.audit.index', ['category' => 'venue']));

        $venueResponse->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('superadmin/audit/index')
                ->has('logs.data', 2)
                ->where('filters.category', 'venue'),
            );

        // Filter by category security
        $securityResponse = $this->actingAs($this->superadmin)
            ->get(route('superadmin.audit.index', ['category' => 'security']));

        $securityResponse->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('superadmin/audit/index')
                ->has('logs.data', 1)
                ->where('filters.category', 'security'),
            );

        // Search text
        $searchResponse = $this->actingAs($this->superadmin)
            ->get(route('superadmin.audit.index', ['search' => 'buram']));

        $searchResponse->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('superadmin/audit/index')
                ->has('logs.data', 1)
                ->where('logs.data.0.action', 'venue.rejected'),
            );
    }

    public function test_audit_log_captures_actor_ip_and_payload_correctly(): void
    {
        $log = AuditService::log(
            action: 'venue.unpublished',
            auditable: null,
            payload: [
                'venue_name' => 'Stadion Test',
                'reason' => 'Pelanggaran ketentuan operasional',
            ],
            actor: $this->superadmin,
        );

        $this->assertDatabaseHas('audit_logs', [
            'id' => $log->id,
            'actor_id' => $this->superadmin->id,
            'action' => 'venue.unpublished',
        ]);

        $this->assertSame('Stadion Test', $log->payload['venue_name'] ?? null);
        $this->assertSame('Pelanggaran ketentuan operasional', $log->payload['reason'] ?? null);
    }

    public function test_non_superadmin_cannot_view_audit_logs(): void
    {
        $owner = User::factory()->create([
            'role' => UserRole::Owner,
        ]);

        $customer = User::factory()->create([
            'role' => UserRole::Customer,
        ]);

        $this->actingAs($owner)
            ->get(route('superadmin.audit.index'))
            ->assertForbidden();

        $this->actingAs($customer)
            ->get(route('superadmin.audit.index'))
            ->assertForbidden();
    }
}
