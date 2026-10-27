<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function createEligibleSubscription(\App\Models\User $owner): \App\Models\Subscription
    {
        $plan = \App\Models\SubscriptionPlan::query()->create([
            'slug' => 'qa-plan-' . strtolower((string) \Illuminate\Support\Str::ulid()),
            'name' => 'QA Eligible Plan',
            'monthly_price' => 200000,
            'duration_days' => 30,
            'max_businesses' => 5,
            'max_venues_per_business' => 10,
            'has_combined_reporting' => true,
            'is_active' => true,
        ]);

        $now = now('UTC');

        return \App\Models\Subscription::query()->create([
            'owner_id' => $owner->getKey(),
            'plan_id' => $plan->getKey(),
            'is_trial' => false,
            'status' => \App\Models\Subscription::STATUS_ACTIVE,
            'starts_at' => $now->copy()->subDay(),
            'ends_at' => $now->copy()->addDays(30),
            'grace_ends_at' => $now->copy()->addDays(31),
        ]);
    }
}
