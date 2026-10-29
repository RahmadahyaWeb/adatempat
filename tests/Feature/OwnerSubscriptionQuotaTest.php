<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Category;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Venue;
use Database\Seeders\SubscriptionPlanSeeder;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class OwnerSubscriptionQuotaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SubscriptionPlanSeeder::class);
        $this->seed(CategorySeeder::class);
    }

    public function test_owner_without_subscription_cannot_create_business_or_venue(): void
    {
        $owner = $this->owner();
        $business = $this->business($owner, 'existing-business');

        $this->assertFalse(Gate::forUser($owner)->allows('create', Business::class));
        $this->assertFalse(Gate::forUser($owner)->allows('create', [Venue::class, $business]));
    }

    public function test_starter_limits_businesses_and_venues_even_when_business_is_draft(): void
    {
        $owner = $this->owner();
        $this->subscribe($owner, 'starter');

        $this->assertTrue(Gate::forUser($owner)->allows('create', Business::class));
        $business = $this->business($owner, 'starter-business');
        $this->assertFalse(Gate::forUser($owner)->allows('create', Business::class));
        $this->assertTrue(Gate::forUser($owner)->allows('create', [Venue::class, $business]));

        $category = Category::query()->firstOrFail();
        $business->venues()->create(['category_id' => $category->getKey(), 'name' => 'Venue A', 'slug' => 'venue-a', 'status' => Venue::STATUS_DRAFT]);
        $business->venues()->create(['category_id' => $category->getKey(), 'name' => 'Venue B', 'slug' => 'venue-b', 'status' => Venue::STATUS_DRAFT]);

        $this->assertFalse(Gate::forUser($owner)->allows('create', [Venue::class, $business]));
    }

    public function test_business_plan_allows_five_businesses_but_only_two_venues_each(): void
    {
        $owner = $this->owner();
        $this->subscribe($owner, 'business');

        for ($index = 1; $index <= 5; $index++) {
            $this->assertTrue(Gate::forUser($owner)->allows('create', Business::class));
            $this->business($owner, 'business-' . $index);
        }

        $this->assertFalse(Gate::forUser($owner)->allows('create', Business::class));
    }

    public function test_pending_payment_does_not_grant_quota(): void
    {
        $owner = $this->owner();
        $this->subscribe($owner, 'starter', Subscription::STATUS_PENDING_PAYMENT);

        $this->assertFalse(Gate::forUser($owner)->allows('create', Business::class));
    }

    private function owner(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_OWNER,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    private function business(User $owner, string $slug): Business
    {
        return $owner->businesses()->create([
            'name' => $slug,
            'slug' => $slug,
            'status' => Business::STATUS_DRAFT,
        ]);
    }

    private function subscribe(User $owner, string $slug, string $status = Subscription::STATUS_ACTIVE): void
    {
        $plan = SubscriptionPlan::query()->where('slug', $slug)->firstOrFail();

        Subscription::query()->create([
            'owner_id' => $owner->getKey(),
            'plan_id' => $plan->getKey(),
            'status' => $status,
            'starts_at' => now('UTC')->subDay(),
            'ends_at' => now('UTC')->addDays(29),
            'grace_ends_at' => now('UTC')->addDays(30),
        ]);
    }
}
