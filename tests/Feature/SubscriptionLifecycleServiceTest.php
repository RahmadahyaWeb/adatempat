<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Subscriptions\SubscriptionLifecycleService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SubscriptionLifecycleServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_grant_one_period_manual_trial_with_audit_log(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $owner = User::factory()->create([
            'role' => User::ROLE_OWNER,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $plan = $this->createPlan();
        $before = CarbonImmutable::now('UTC');

        $subscription = app(SubscriptionLifecycleService::class)->grantManualTrial(
            $admin,
            $owner,
            $plan,
            'Trial diberikan setelah proses akuisisi Owner melalui kunjungan lapangan.',
        );

        $this->assertSame(Subscription::STATUS_TRIALING, $subscription->status);
        $this->assertTrue($subscription->is_trial);
        $this->assertSame($owner->getKey(), $subscription->owner_id);
        $this->assertSame($plan->getKey(), $subscription->plan_id);
        $this->assertTrue($subscription->starts_at->greaterThanOrEqualTo($before));
        $this->assertSame(
            $subscription->starts_at->copy()->addDays($plan->duration_days)->timestamp,
            $subscription->ends_at->timestamp,
        );
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Subscription::class,
            'auditable_id' => $subscription->getKey(),
            'action' => 'subscription.manual_trial_granted',
        ]);
    }

    public function test_owner_with_running_subscription_cannot_receive_overlapping_trial(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $owner = User::factory()->create([
            'role' => User::ROLE_OWNER,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $plan = $this->createPlan();

        Subscription::query()->create([
            'owner_id' => $owner->getKey(),
            'plan_id' => $plan->getKey(),
            'is_trial' => false,
            'status' => Subscription::STATUS_ACTIVE,
            'starts_at' => now('UTC')->subDay(),
            'ends_at' => now('UTC')->addDays(10),
            'grace_ends_at' => now('UTC')->addDays(11),
        ]);

        $this->expectException(ValidationException::class);

        app(SubscriptionLifecycleService::class)->grantManualTrial(
            $admin,
            $owner,
            $plan,
            'Trial tidak boleh menumpuk pada langganan aktif.',
        );
    }

    public function test_expired_subscription_enters_grace_then_is_suspended_after_grace_deadline(): void
    {
        $owner = User::factory()->create([
            'role' => User::ROLE_OWNER,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $plan = $this->createPlan();

        $subscription = Subscription::query()->create([
            'owner_id' => $owner->getKey(),
            'plan_id' => $plan->getKey(),
            'is_trial' => false,
            'status' => Subscription::STATUS_ACTIVE,
            'starts_at' => now('UTC')->subDays(31),
            'ends_at' => now('UTC')->subMinute(),
            'grace_ends_at' => now('UTC')->addDays(2),
        ]);

        $service = app(SubscriptionLifecycleService::class);
        $first = $service->processDue();

        $this->assertSame(1, $first['grace_started']);
        $this->assertSame(Subscription::STATUS_GRACE_PERIOD, $subscription->fresh()->status);
        $this->assertEquals(
            $subscription->fresh()->ends_at->copy()->addHours(24)->timestamp,
            $subscription->fresh()->grace_ends_at->timestamp,
        );

        $subscription->update(['grace_ends_at' => now('UTC')->subSecond()]);
        $second = $service->processDue();

        $this->assertSame(1, $second['suspended']);
        $this->assertSame(Subscription::STATUS_SUSPENDED, $subscription->fresh()->status);
        $this->assertNotNull($subscription->fresh()->suspended_at);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Subscription::class,
            'auditable_id' => $subscription->getKey(),
            'action' => 'subscription.suspended_after_grace',
        ]);
    }

    private function createPlan(): SubscriptionPlan
    {
        return SubscriptionPlan::query()->create([
            'slug' => 'trial-plan-' . strtolower(bin2hex(random_bytes(4))),
            'name' => 'Trial Plan',
            'monthly_price' => 100000,
            'duration_days' => 30,
            'max_businesses' => 2,
            'max_venues_per_business' => 5,
            'has_combined_reporting' => false,
            'is_active' => true,
        ]);
    }
}
