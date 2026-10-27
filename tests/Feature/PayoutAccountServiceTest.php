<?php

namespace Tests\Feature;

use App\Models\PayoutAccount;
use App\Models\User;
use App\Services\Finance\PayoutAccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayoutAccountServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_account_number_is_encrypted_and_hidden_from_serialization(): void
    {
        $owner = User::factory()->create([
            'role' => User::ROLE_OWNER,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $account = app(PayoutAccountService::class)->createForOwner(
            $owner,
            'bca',
            'Bank Central Asia',
            '123456789012',
            'Pemilik Uji',
        );

        $stored = PayoutAccount::query()->findOrFail($account->getKey())->getRawOriginal('account_number_encrypted');

        $this->assertNotSame('123456789012', $stored);
        $this->assertSame('123456789012', $account->account_number_encrypted);
        $this->assertSame('9012', $account->account_number_last4);
        $this->assertArrayNotHasKey('account_number_encrypted', $account->toArray());
        $this->assertSame(PayoutAccount::STATUS_PENDING, $account->verification_status);
    }

    public function test_super_admin_can_verify_pending_account_with_audit_log(): void
    {
        $owner = User::factory()->create([
            'role' => User::ROLE_OWNER,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $admin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $account = app(PayoutAccountService::class)->createForOwner(
            $owner,
            'BCA',
            'Bank Central Asia',
            '123456789012',
            'Pemilik Uji',
        );

        $verified = app(PayoutAccountService::class)->review($account, $admin, 'verify', 'Nama dan rekening sesuai bukti.');

        $this->assertSame(PayoutAccount::STATUS_VERIFIED, $verified->verification_status);
        $this->assertSame($admin->getKey(), $verified->verified_by);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => PayoutAccount::class,
            'auditable_id' => $account->getKey(),
            'action' => 'payout_account.reviewed',
            'actor_user_id' => $admin->getKey(),
        ]);
    }
}
