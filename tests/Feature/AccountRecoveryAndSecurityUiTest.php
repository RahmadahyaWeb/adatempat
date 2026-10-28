<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\TestCase;

class AccountRecoveryAndSecurityUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_page_uses_consistent_copy(): void
    {
        Livewire::test('pages::auth.forgot-password')
            ->assertSee('Lupa kata sandi')
            ->assertSee('Kirim instruksi')
            ->assertSee('tidak akan mengungkap apakah sebuah email terdaftar.');
    }

    public function test_reset_password_page_uses_consistent_copy_and_fields(): void
    {
        Livewire::test('pages::auth.reset-password', ['token' => 'sample-reset-token'])
            ->assertSee('Atur ulang kata sandi')
            ->assertSee('Alamat email')
            ->assertSee('Kata sandi baru')
            ->assertSee('Konfirmasi kata sandi')
            ->assertSee('Simpan kata sandi');
    }

    public function test_email_verification_page_shows_the_current_account_email_and_actions(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $owner->forceFill([
            'status' => 'active',
            'email_verified_at' => null,
        ])->save();

        Livewire::actingAs($owner)
            ->test('pages::auth.verify-email')
            ->assertSee('Verifikasi emailmu.')
            ->assertSee($owner->email)
            ->assertSee('Kirim ulang tautan')
            ->assertSee('Keluar dari akun');
    }


    public function test_email_verification_returns_user_to_intended_destination(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $customer->forceFill([
            'status' => 'active',
            'email_verified_at' => null,
        ])->save();

        $intended = route('customer.bookings.index');

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(30),
            [
                'publicId' => $customer->public_id,
                'hash' => sha1($customer->getEmailForVerification()),
            ],
        );

        $this->actingAs($customer)
            ->withSession(['url.intended' => $intended])
            ->get($verificationUrl)
            ->assertRedirect($intended);

        $this->assertNotNull($customer->fresh()->email_verified_at);
    }

    public function test_mfa_setup_page_explains_both_steps_and_secret_safety(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $admin->forceFill([
            'status' => 'active',
            'mfa_secret' => null,
            'mfa_enabled_at' => null,
        ])->save();

        Livewire::actingAs($admin)
            ->test('pages::admin.mfa.setup')
            ->assertSee('Amankan akun Super Admin')
            ->assertSee('Langkah 1 dari 2')
            ->assertSee('Secret TOTP')
            ->assertSee('Langkah 2 dari 2')
            ->assertSee('Kode verifikasi (6 digit)')
            ->assertSee('Aktifkan verifikasi dua langkah');
    }

    public function test_mfa_challenge_page_keeps_the_verification_and_safety_guidance(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $admin->forceFill([
            'status' => 'active',
            'mfa_secret' => 'JBSWY3DPEHPK3PXP',
            'mfa_enabled_at' => now(),
        ])->save();

        Livewire::actingAs($admin)
            ->test('pages::admin.mfa.challenge')
            ->assertSee('Verifikasi akses')
            ->assertSee('Kode verifikasi (6 digit)')
            ->assertSee('Verifikasi dan lanjutkan')
            ->assertSee('Jangan bagikan kode kepada siapa pun.');
    }
}
