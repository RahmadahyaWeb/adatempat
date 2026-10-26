<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Category;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Venue;
use App\Services\Subscriptions\SubscriptionEligibilityService;
use Carbon\CarbonImmutable;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SubscriptionEligibilityServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CategorySeeder::class);
    }

    public function test_active_subscription_makes_published_business_eligible_for_booking(): void
    {
        [$owner, $business, $plan] = $this->createBusinessAndPlan();
        $at = CarbonImmutable::now('UTC');

        $this->createSubscription($owner, $plan, [
            'status' => Subscription::STATUS_ACTIVE,
            'starts_at' => $at->subDay(),
            'ends_at' => $at->addDays(10),
            'grace_ends_at' => $at->addDays(17),
        ]);

        $eligible = app(SubscriptionEligibilityService::class)->eligibleForBusiness($business, $at);

        $this->assertNotNull($eligible);
        $this->assertSame(Subscription::STATUS_ACTIVE, $eligible->status);
    }

    public function test_grace_period_is_eligible_until_its_deadline(): void
    {
        [$owner, $business, $plan] = $this->createBusinessAndPlan();
        $at = CarbonImmutable::now('UTC');

        $this->createSubscription($owner, $plan, [
            'status' => Subscription::STATUS_GRACE_PERIOD,
            'starts_at' => $at->subDays(40),
            'ends_at' => $at->subDays(10),
            'grace_ends_at' => $at->addDay(),
        ]);

        $this->assertNotNull(app(SubscriptionEligibilityService::class)->eligibleForBusiness($business, $at));
        $this->assertNull(app(SubscriptionEligibilityService::class)->eligibleForBusiness($business, $at->addDays(2)));
    }

    public function test_expired_or_pending_payment_subscription_is_not_eligible(): void
    {
        [$owner, $business, $plan] = $this->createBusinessAndPlan();
        $at = CarbonImmutable::now('UTC');

        $this->createSubscription($owner, $plan, [
            'status' => Subscription::STATUS_ACTIVE,
            'starts_at' => $at->subDays(40),
            'ends_at' => $at->subDay(),
            'grace_ends_at' => $at->subHour(),
        ]);

        $this->assertNull(app(SubscriptionEligibilityService::class)->eligibleForBusiness($business, $at));
    }

    public function test_retained_business_restriction_and_venue_limit_are_enforced(): void
    {
        [$owner, $business, $plan] = $this->createBusinessAndPlan(['max_venues_per_business' => 1]);
        $otherBusiness = $owner->businesses()->create([
            'name' => 'Usaha Kedua',
            'slug' => 'usaha-kedua-' . strtolower((string) Str::ulid()),
            'address' => 'Alamat kedua',
            'timezone' => 'Asia/Jakarta',
            'status' => Business::STATUS_ACTIVE,
            'published_at' => now(),
        ]);
        $at = CarbonImmutable::now('UTC');

        $this->createSubscription($owner, $plan, [
            'status' => Subscription::STATUS_ACTIVE,
            'starts_at' => $at->subDay(),
            'ends_at' => $at->addDays(10),
            'grace_ends_at' => $at->addDays(17),
            'retained_business_id' => $business->getKey(),
        ]);

        $this->assertNotNull(app(SubscriptionEligibilityService::class)->eligibleForBusiness($business, $at));
        $this->assertNull(app(SubscriptionEligibilityService::class)->eligibleForBusiness($otherBusiness, $at));

        $category = Category::query()->firstOrFail();
        $business->venues()->create([
            'category_id' => $category->getKey(),
            'name' => 'Venue Batas',
            'slug' => 'venue-batas-' . strtolower((string) Str::ulid()),
            'status' => Venue::STATUS_DRAFT,
        ]);

        $this->assertFalse(app(SubscriptionEligibilityService::class)->canCreateVenue($business, $at));
    }

    private function createBusinessAndPlan(array $planOverrides = []): array
    {
        $owner = User::factory()->create([
            'role' => User::ROLE_OWNER,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $business = $owner->businesses()->create([
            'name' => 'Usaha Langganan Uji',
            'slug' => 'usaha-langganan-' . strtolower((string) Str::ulid()),
            'address' => 'Jalan Contoh Nomor 10',
            'timezone' => 'Asia/Jakarta',
            'status' => Business::STATUS_ACTIVE,
            'published_at' => now(),
        ]);
        $plan = SubscriptionPlan::query()->create(array_merge([
            'slug' => 'plan-uji-' . strtolower((string) Str::ulid()),
            'name' => 'Plan Uji',
            'monthly_price' => 100000,
            'duration_days' => 30,
            'max_businesses' => 2,
            'max_venues_per_business' => 5,
            'has_combined_reporting' => false,
            'is_active' => true,
        ], $planOverrides));

        return [$owner, $business, $plan];
    }

    private function createSubscription(User $owner, SubscriptionPlan $plan, array $overrides = []): Subscription
    {
        $now = CarbonImmutable::now('UTC');

        return Subscription::query()->create(array_merge([
            'owner_id' => $owner->getKey(),
            'plan_id' => $plan->getKey(),
            'scheduled_plan_id' => null,
            'retained_business_id' => null,
            'is_trial' => false,
            'status' => Subscription::STATUS_ACTIVE,
            'starts_at' => $now->subDay(),
            'ends_at' => $now->addDays(30),
            'grace_ends_at' => $now->addDays(37),
            'suspended_at' => null,
            'reverified_by' => null,
        ], $overrides));
    }
}
