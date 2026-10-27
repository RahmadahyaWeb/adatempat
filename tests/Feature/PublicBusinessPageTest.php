<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Category;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PublicBusinessPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_business_page_prioritizes_business_identity_and_comparable_venue_information(): void
    {
        [$business, $venue] = $this->publishedBusinessWithVenue();

        $venue->forceFill([
            'capacity_persons' => 120,
            'facilities_json' => ['Wi-Fi', 'AC', 'Parkir'],
        ])->save();

        $venue->prices()->create([
            'pricing_unit' => 'hourly',
            'step_interval_minutes' => 60,
            'min_duration_units' => 1,
            'max_duration_units' => 8,
            'price_amount' => 250000,
            'weekend_price_amount' => 300000,
            'is_active' => true,
        ]);

        Livewire::test('pages::business', ['businessSlug' => $business->slug])
            ->assertSee($business->name)
            ->assertSee('Usaha terverifikasi')
            ->assertSee('Venue Meeting Publik')
            ->assertSee('120 orang')
            ->assertSee('Wi-Fi')
            ->assertSee('Rp 250.000')
            ->assertSee('Detail venue dan jam operasional');
    }

    public function test_public_business_page_does_not_show_unpublished_venues(): void
    {
        [$business] = $this->publishedBusinessWithVenue();

        $category = Category::query()->firstOrFail();

        $business->venues()->create([
            'category_id' => $category->getKey(),
            'name' => 'Venue Draft Rahasia',
            'slug' => 'venue-draft-rahasia',
            'status' => Venue::STATUS_DRAFT,
        ]);

        Livewire::test('pages::business', ['businessSlug' => $business->slug])
            ->assertSee('Venue Meeting Publik')
            ->assertDontSee('Venue Draft Rahasia');
    }

    public function test_inactive_business_is_not_publicly_accessible(): void
    {
        [$business] = $this->publishedBusinessWithVenue();

        $business->forceFill(['status' => Business::STATUS_SUSPENDED])->save();

        Livewire::test('pages::business', ['businessSlug' => $business->slug])
            ->assertNotFound();
    }

    private function publishedBusinessWithVenue(): array
    {
        $owner = User::factory()->create([
            'role' => User::ROLE_OWNER,
            'status' => 'active',
        ]);

        $plan = SubscriptionPlan::query()->create([
            'slug' => 'starter-test',
            'name' => 'Starter Test',
            'monthly_price' => 100000,
            'duration_days' => 30,
            'max_businesses' => 1,
            'max_venues_per_business' => 5,
            'has_combined_reporting' => false,
            'is_active' => true,
        ]);

        Subscription::query()->create([
            'owner_id' => $owner->getKey(),
            'plan_id' => $plan->getKey(),
            'is_trial' => false,
            'status' => Subscription::STATUS_ACTIVE,
            'starts_at' => now('UTC')->subDay(),
            'ends_at' => now('UTC')->addDays(29),
        ]);

        $business = $owner->businesses()->create([
            'name' => 'Studio Publik Adatempat',
            'slug' => 'studio-publik-adatempat',
            'address' => 'Jl. Contoh No. 1',
            'city' => 'Banjarbaru',
            'province' => 'Kalimantan Selatan',
            'status' => Business::STATUS_ACTIVE,
            'verified_at' => now(),
            'published_at' => now(),
        ]);

        $category = Category::query()->create([
            'name' => 'Meeting',
            'slug' => 'meeting',
            'status' => Category::STATUS_ACTIVE,
        ]);

        $venue = $business->venues()->create([
            'category_id' => $category->getKey(),
            'name' => 'Venue Meeting Publik',
            'slug' => 'venue-meeting-publik',
            'description' => 'Venue untuk meeting dan kegiatan tim.',
            'status' => Venue::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        return [$business, $venue];
    }
}
