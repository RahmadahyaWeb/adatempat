<?php

namespace Tests\Feature;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Subscriptions\SubscriptionBillingService;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerSubscriptionOfferTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SubscriptionPlanSeeder::class);
    }

    public function test_active_starter_owner_sees_business_upgrade_and_separate_renewal(): void
    {
        $owner = $this->owner();
        $starter = SubscriptionPlan::query()->where('slug', 'starter')->firstOrFail();
        $this->subscribe($owner, $starter);

        $this->actingAs($owner)
            ->get(route('owner.subscription'))
            ->assertOk()
            ->assertSee('Upgrade paket')
            ->assertSee('Upgrade ke Business')
            ->assertSee('Perpanjang Starter')
            ->assertDontSee('value="'.$starter->getKey().'"', false);
    }

    public function test_active_business_owner_sees_downgrade_and_separate_renewal(): void
    {
        $owner = $this->owner();
        $business = SubscriptionPlan::query()->where('slug', 'business')->firstOrFail();
        $this->subscribe($owner, $business);

        $this->actingAs($owner)
            ->get(route('owner.subscription'))
            ->assertOk()
            ->assertSee('Ubah paket')
            ->assertSee('Perpanjang Business')
            ->assertDontSee('value="'.$business->getKey().'"', false);
    }

    public function test_owner_without_subscription_can_choose_initial_plan(): void
    {
        $this->actingAs($this->owner())
            ->get(route('owner.subscription'))
            ->assertOk()
            ->assertSee('Pilih paket')
            ->assertSee('Starter')
            ->assertSee('Business')
            ->assertDontSee('Perpanjang Starter');
    }

    public function test_pending_upgrade_is_shown_after_reload_and_additional_checkout_is_blocked(): void
    {
        $owner = $this->owner();
        $starter = SubscriptionPlan::query()->where('slug', 'starter')->firstOrFail();
        $business = SubscriptionPlan::query()->where('slug', 'business')->firstOrFail();
        $this->subscribe($owner, $starter);

        $checkout = app(SubscriptionBillingService::class)->checkoutPlan(
            $owner,
            $business,
            'owner-subscription-ui-pending-upgrade',
        );

        $this->actingAs($owner)
            ->get(route('owner.subscription'))
            ->assertOk()
            ->assertSee('Upgrade menunggu pembayaran')
            ->assertSee($checkout['invoice']->invoice_number)
            ->assertSee('Pembayaran simulasi (lokal)')
            ->assertSee('Masih ada tagihan yang menunggu pembayaran.')
            ->assertDontSee('Perpanjang Starter');
    }

    private function owner(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_OWNER,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    private function subscribe(User $owner, SubscriptionPlan $plan): void
    {
        Subscription::query()->create([
            'owner_id' => $owner->getKey(),
            'plan_id' => $plan->getKey(),
            'status' => Subscription::STATUS_ACTIVE,
            'starts_at' => now('UTC')->subDay(),
            'ends_at' => now('UTC')->addDays(29),
            'grace_ends_at' => now('UTC')->addDays(30),
        ]);
    }
}
