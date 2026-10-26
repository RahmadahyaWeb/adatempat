<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Category;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenuePrice;
use Database\Seeders\CategorySeeder;
use Database\Seeders\VenueTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class VenuePricingAndAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CategorySeeder::class);
        $this->seed(VenueTemplateSeeder::class);
    }

    public function test_owner_can_save_weekly_hours_and_hourly_pricing(): void
    {
        $owner = $this->createOwner();
        $business = $this->createBusiness($owner);
        $category = Category::query()->where('slug', 'acara')->firstOrFail();
        $template = $category->venueTemplates()->where('is_active', true)->firstOrFail();
        $rules = $this->defaultAvailabilityRules();
        $rules[1]['is_open'] = true;
        $rules[1]['open_time'] = '08:00';
        $rules[1]['close_time'] = '22:00';
        $rules[1]['buffer_minutes'] = '30';

        Livewire::actingAs($owner)
            ->test('pages::owner.venue-form', ['business' => $business->public_id])
            ->set('name', 'Venue Jam Uji')
            ->set('categoryId', (string) $category->getKey())
            ->set('venueTemplateId', (string) $template->getKey())
            ->set('templateValues', [
                'stage_type' => 'Fleksibel',
                'sound_system' => true,
                'parking_spaces' => '10',
            ])
            ->set('availabilityRules', $rules)
            ->set('pricingUnit', VenuePrice::UNIT_HOURLY)
            ->set('priceRows', [[
                'id' => null,
                'session_name' => '',
                'session_start_time' => '09:00',
                'session_end_time' => '17:00',
                'step_interval_minutes' => '30',
                'min_duration_units' => '2',
                'max_duration_units' => '12',
                'price_amount' => '250000',
                'weekend_price_amount' => '300000',
            ]])
            ->call('save')
            ->assertHasNoErrors();

        $venue = $business->venues()->where('name', 'Venue Jam Uji')->firstOrFail();

        $this->assertDatabaseHas('venue_availability_rules', [
            'venue_id' => $venue->getKey(),
            'day_of_week' => 1,
            'is_open' => 1,
            'open_time' => '08:00:00',
            'close_time' => '22:00:00',
            'buffer_minutes' => 30,
        ]);
        $this->assertSame(7, $venue->availabilityRules()->count());
        $this->assertDatabaseHas('venue_prices', [
            'venue_id' => $venue->getKey(),
            'pricing_unit' => VenuePrice::UNIT_HOURLY,
            'step_interval_minutes' => 30,
            'min_duration_units' => 2,
            'max_duration_units' => 12,
            'price_amount' => 250000,
            'weekend_price_amount' => 300000,
            'is_active' => 1,
        ]);
    }

    public function test_owner_cannot_save_open_hours_with_identical_open_and_close_times(): void
    {
        $owner = $this->createOwner();
        $business = $this->createBusiness($owner);
        $category = Category::query()->where('slug', 'acara')->firstOrFail();
        $template = $category->templates()->where('is_active', true)->firstOrFail();
        $rules = $this->defaultAvailabilityRules();
        $rules[1]['is_open'] = true;
        $rules[1]['open_time'] = '09:00';
        $rules[1]['close_time'] = '09:00';

        Livewire::actingAs($owner)
            ->test('pages::owner.venue-form', ['business' => $business->public_id])
            ->set('name', 'Venue Jam Invalid')
            ->set('categoryId', (string) $category->getKey())
            ->set('venueTemplateId', (string) $template->getKey())
            ->set('availabilityRules', $rules)
            ->call('save')
            ->assertHasErrors(['availabilityRules.1.close_time']);

        $this->assertDatabaseMissing('venues', [
            'business_id' => $business->getKey(),
            'name' => 'Venue Jam Invalid',
        ]);
    }

    public function test_owner_cannot_save_price_with_maximum_duration_below_minimum(): void
    {
        $owner = $this->createOwner();
        $business = $this->createBusiness($owner);
        $category = Category::query()->where('slug', 'acara')->firstOrFail();
        $template = $category->templates()->where('is_active', true)->firstOrFail();

        Livewire::actingAs($owner)
            ->test('pages::owner.venue-form', ['business' => $business->public_id])
            ->set('name', 'Venue Harga Invalid')
            ->set('categoryId', (string) $category->getKey())
            ->set('venueTemplateId', (string) $template->getKey())
            ->set('pricingUnit', VenuePrice::UNIT_DAILY)
            ->set('priceRows', [[
                'id' => null,
                'session_name' => '',
                'session_start_time' => '09:00',
                'session_end_time' => '17:00',
                'step_interval_minutes' => '60',
                'min_duration_units' => '5',
                'max_duration_units' => '2',
                'price_amount' => '500000',
                'weekend_price_amount' => '',
            ]])
            ->call('save')
            ->assertHasErrors(['priceRows.0.max_duration_units']);

        $this->assertDatabaseMissing('venues', [
            'business_id' => $business->getKey(),
            'name' => 'Venue Harga Invalid',
        ]);
    }

    public function test_changing_pricing_model_deactivates_old_prices_without_deleting_history(): void
    {
        $owner = $this->createOwner();
        $business = $this->createBusiness($owner);
        $category = Category::query()->where('slug', 'acara')->firstOrFail();
        $venue = $business->venues()->create([
            'category_id' => $category->getKey(),
            'name' => 'Venue Ganti Model Harga',
            'slug' => 'venue-ganti-model-harga',
            'status' => Venue::STATUS_DRAFT,
        ]);
        $oldPrice = $venue->prices()->create([
            'pricing_unit' => VenuePrice::UNIT_HOURLY,
            'step_interval_minutes' => 60,
            'min_duration_units' => 1,
            'max_duration_units' => 10,
            'price_amount' => 100000,
            'is_active' => true,
        ]);
        $template = $category->templates()->where('is_active', true)->firstOrFail();

        Livewire::actingAs($owner)
            ->test('pages::owner.venue-form', [
                'business' => $business->public_id,
                'venue' => $venue->public_id,
            ])
            ->set('pricingUnit', VenuePrice::UNIT_DAILY)
            ->set('priceRows', [[
                'id' => null,
                'session_name' => '',
                'session_start_time' => '09:00',
                'session_end_time' => '17:00',
                'step_interval_minutes' => '60',
                'min_duration_units' => '1',
                'max_duration_units' => '5',
                'price_amount' => '700000',
                'weekend_price_amount' => null,
            ]])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('venue_prices', [
            'id' => $oldPrice->getKey(),
            'is_active' => 0,
        ]);
        $this->assertDatabaseHas('venue_prices', [
            'venue_id' => $venue->getKey(),
            'pricing_unit' => VenuePrice::UNIT_DAILY,
            'price_amount' => 700000,
            'is_active' => 1,
        ]);
    }

    private function createOwner(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_OWNER,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    private function createBusiness(User $owner): Business
    {
        return $owner->businesses()->create([
            'name' => 'Usaha Venue Uji',
            'slug' => 'usaha-venue-uji-' . strtolower((string) Str::ulid()),
            'address' => 'Jalan Contoh Nomor 10',
            'status' => Business::STATUS_DRAFT,
            'published_at' => null,
        ]);
    }

    private function defaultAvailabilityRules(): array
    {
        $rules = [];

        for ($day = 0; $day <= 6; $day++) {
            $rules[$day] = [
                'day_of_week' => $day,
                'is_open' => false,
                'open_time' => '09:00',
                'close_time' => '17:00',
                'buffer_minutes' => '0',
            ];
        }

        return $rules;
    }
}
