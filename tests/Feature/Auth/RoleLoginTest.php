<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_mvp_roles_can_log_in(): void
    {
        foreach (['customer', 'owner', 'superadmin'] as $role) {
            $user = User::factory()->{$role}()->create();

            $this->post(route('login.store'), [
                'email' => $user->email,
                'password' => 'password',
            ])->assertRedirect();

            $this->assertAuthenticatedAs($user);

            $this->post(route('logout'));
        }
    }
}
