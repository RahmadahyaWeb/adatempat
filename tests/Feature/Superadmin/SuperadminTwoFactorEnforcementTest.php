<?php

namespace Tests\Feature\Superadmin;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Tests\TestCase;

class SuperadminTwoFactorEnforcementTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_without_two_factor_is_redirected_to_security_settings(): void
    {
        $superadmin = User::factory()->create([
            'role' => UserRole::Superadmin,
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
        ]);

        $response = $this->actingAs($superadmin)
            ->get(route('superadmin.venues.index'));

        $response->assertRedirect(route('security.edit'));
        $response->assertSessionHas('error', 'Akun Superadmin wajib mengaktifkan Autentikasi Dua Faktor (2FA) sebelum dapat mengakses panel admin.');
    }

    public function test_superadmin_without_two_factor_receives_403_on_json_request(): void
    {
        $superadmin = User::factory()->create([
            'role' => UserRole::Superadmin,
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
        ]);

        $response = $this->actingAs($superadmin)
            ->getJson(route('superadmin.venues.index'));

        $response->assertForbidden();
        $response->assertJson([
            'message' => 'Autentikasi dua faktor (2FA) wajib diaktifkan untuk akun superadmin.',
        ]);
    }

    public function test_superadmin_with_confirmed_two_factor_can_access_superadmin_panel(): void
    {
        $superadmin = User::factory()->create([
            'role' => UserRole::Superadmin,
            'two_factor_secret' => 'dummy-encrypted-secret',
            'two_factor_confirmed_at' => now(),
        ]);

        $response = $this->actingAs($superadmin)
            ->get(route('superadmin.venues.index'));

        $response->assertOk();
    }

    public function test_confirming_two_factor_records_audit_log(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Superadmin,
            'email' => 'superadmin.audit@adatempat.test',
        ]);

        event(new TwoFactorAuthenticationConfirmed($user));

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $user->id,
            'action' => 'user.2fa_enabled',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
        ]);

        $log = AuditLog::where('action', 'user.2fa_enabled')->first();
        $this->assertNotNull($log);
        $this->assertSame('superadmin.audit@adatempat.test', $log->payload['email'] ?? null);
    }

    public function test_disabling_two_factor_records_audit_log(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Superadmin,
            'email' => 'superadmin.disabled@adatempat.test',
        ]);

        event(new TwoFactorAuthenticationDisabled($user));

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $user->id,
            'action' => 'user.2fa_disabled',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
        ]);

        $log = AuditLog::where('action', 'user.2fa_disabled')->first();
        $this->assertNotNull($log);
        $this->assertSame('superadmin.disabled@adatempat.test', $log->payload['email'] ?? null);
    }
}
