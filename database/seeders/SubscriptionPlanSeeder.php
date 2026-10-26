<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'slug' => 'starter',
                'name' => 'Starter',
                'monthly_price' => 200000,
                'duration_days' => 30,
                'max_businesses' => 1,
                'max_venues_per_business' => 2,
                'has_combined_reporting' => false,
                'is_active' => true,
            ],
            [
                'slug' => 'business',
                'name' => 'Business',
                'monthly_price' => 500000,
                'duration_days' => 30,
                'max_businesses' => 5,
                'max_venues_per_business' => 2,
                'has_combined_reporting' => true,
                'is_active' => true,
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::firstOrCreate(
                ['slug' => $plan['slug']],
                $plan,
            );
        }
    }
}
