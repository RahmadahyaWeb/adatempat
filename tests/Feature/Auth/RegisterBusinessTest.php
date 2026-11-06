<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class RegisterBusinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_registration_screen_can_be_rendered(): void
    {
        $this->get(route('register.business'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('auth/register-business')
                ->has('passwordRules'));
    }

    public function test_owner_and_business_are_created(): void
    {
        Event::fake([Registered::class]);

        $response = $this->post(route('register.business.store'), [
            'name' => 'Owner Baru',
            'email' => 'Owner@Example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'business_name' => 'Venue Test',
            'legal_name' => 'PT Venue Test',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();

        $user = User::query()->where('email', 'owner@example.com')->firstOrFail();

        $this->assertSame(UserRole::Owner, $user->role);
        $this->assertDatabaseHas('businesses', [
            'user_id' => $user->id,
            'name' => 'Venue Test',
            'legal_name' => 'PT Venue Test',
            'status' => 'active',
        ]);
        Event::assertDispatched(Registered::class);
    }

    public function test_email_must_be_unique(): void
    {
        User::factory()->create(['email' => 'owner@example.com']);

        $this->post(route('register.business.store'), [
            'name' => 'Owner Baru',
            'email' => 'owner@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'business_name' => 'Venue Test',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertDatabaseCount('businesses', 0);
    }

    public function test_business_name_is_required(): void
    {
        $this->post(route('register.business.store'), [
            'name' => 'Owner Baru',
            'email' => 'owner@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors('business_name');

        $this->assertGuest();
    }
}
