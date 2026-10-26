<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Category;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenuePrice;
use App\Models\VenueTemplate;
use Database\Seeders\CategorySeeder;
use Database\Seeders\VenueTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class VenueOperationalSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CategorySeeder::class);
        $this->seed(VenueTemplateSeeder::class);
    }

    public function test_owner_can_save_overnight_hours_and_hourly_pricing(): void
    {
        $owner = $this->createOwner();
        [$business, $venue, $template] = $this->createVenue($owner);

        Livewire::actingAs($owner)
            ->test('pages::owner.venue-form', [
                'business' => $business->public_id,
                'venue' => $venue->public_id,
            ])
            ->set('availabilityRules.1.is_open', true)
            ->set('availabilityRules.1.open_time', '22:00')
            ->set('availabilityRules.1.close_time', '02:00')
            ->set('availabilityRules.1.buffer_minutes', '30')
            ->set('pricingUnit', VenuePrice::UNIT_HOURLY)
            ->set('priceRows', [[
                'id' => null,
                'session_name' => '',
                'session_start_time' => '09:00',
                'session_end_time' => '17:00',
                'step_interval_minutes' => '30',
                'min_duration_units' => '2',
                'max_duration_units' => '8',
                'price_amount' => '250000',
                'weekend_price_amount' => '300000',
            ]])
            ->set('templateValues', $this->validTemplateValues())
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('venue_availability_rules', [
            'venue_id' => $venue->getKey(),
            'day_of_week' => 1,
            'is_open' => 1,
            'open_time' => '22:00:00',
            'close_time' => '02:00:00',
            'buffer_minutes' => 30,
        ]);

        $price = $venue->prices()->where('is_active', true)->firstOrFail();
        $this->assertSame(VenuePrice::UNIT_HOURLY, $price->pricing_unit);
        $this->assertSame(250000, $price->price_amount);
        $this->assertSame(300000, $price->weekend_price_amount);
        $this->assertSame(30, $price->step_interval_minutes);
        $this->assertSame(2, $price->min_duration_units);
        $this->assertSame(8, $price->max_duration_units);
        $this->assertSame('Asia/Jakarta', $business->fresh()->timezone);
    }

    public function test_owner_cannot_set_equal_open_and_close_times_for_an_open_day(): void
    {
        $owner = $this->createOwner();
        [$business, $venue] = $this->createVenue($owner);

        Livewire::actingAs($owner)
            ->test('pages::owner.venue-form', [
                'business' => $business->public_id,
                'venue' => $venue->public_id,
            ])
            ->set('availabilityRules.1.is_open', true)
            ->set('availabilityRules.1.open_time', '09:00')
            ->set('availabilityRules.1.close_time', '09:00')
            ->set('templateValues', $this->validTemplateValues())
            ->call('save')
            ->assertHasErrors(['availabilityRules.1.close_time']);

        $this->assertDatabaseMissing('venue_availability_rules', [
            'venue_id' => $venue->getKey(),
            'day_of_week' => 1,
            'is_open' => 1,
        ]);
    }

    public function test_switching_pricing_model_deactivates_old_rows_and_supports_multiple_sessions(): void
    {
        $owner = $this->createOwner();
        [$business, $venue] = $this->createVenue($owner);
        $oldPrice = $venue->prices()->create([
            'pricing_unit' => VenuePrice::UNIT_HOURLY,
            'step_interval_minutes' => 60,
            'min_duration_units' => 1,
            'max_duration_units' => 12,
            'price_amount' => 150000,
            'weekend_price_amount' => null,
            'is_active' => true,
        ]);

        Livewire::actingAs($owner)
            ->test('pages::owner.venue-form', [
                'business' => $business->public_id,
                'venue' => $venue->public_id,
            ])
            ->set('pricingUnit', VenuePrice::UNIT_SESSION)
            ->set('priceRows', [
                [
                    'id' => null,
                    'session_name' => 'Sesi pagi',
                    'session_start_time' => '08:00',
                    'session_end_time' => '12:00',
                    'step_interval_minutes' => '60',
                    'min_duration_units' => '1',
                    'max_duration_units' => '24',
                    'price_amount' => '300000',
                    'weekend_price_amount' => '',
                ],
                [
                    'id' => null,
                    'session_name' => 'Sesi siang',
                    'session_start_time' => '13:00',
                    'session_end_time' => '17:00',
                    'step_interval_minutes' => '60',
                    'min_duration_units' => '1',
                    'max_duration_units' => '24',
                    'price_amount' => '350000',
                    'weekend_price_amount' => '400000',
                ],
            ])
            ->set('templateValues', $this->validTemplateValues())
            ->call('save')
            ->assertHasNoErrors();

        $this->assertFalse($oldPrice->fresh()->is_active);
        $activePrices = $venue->prices()->where('is_active', true)->orderBy('id')->get();
        $this->assertCount(2, $activePrices);
        $this->assertSame(
            [VenuePrice::UNIT_SESSION, VenuePrice::UNIT_SESSION],
            $activePrices->pluck('pricing_unit')->all(),
        );
        $this->assertSame(['Sesi pagi', 'Sesi siang'], $activePrices->pluck('session_name')->all());
    }

    public function test_owner_can_set_business_timezone_for_local_operating_hours(): void
    {
        $owner = $this->createOwner();
        [$business] = $this->createVenue($owner);

        Livewire::actingAs($owner)
            ->test('pages::owner.business-edit', ['business' => $business->public_id])
            ->set('timezone', 'Asia/Makassar')
            ->call('saveTimezone')
            ->assertHasNoErrors()
            ->assertSee('Zona waktu usaha berhasil diperbarui');

        $this->assertDatabaseHas('businesses', [
            'id' => $business->getKey(),
            'timezone' => 'Asia/Makassar',
        ]);
    }

    public function test_public_profile_displays_the_active_price(): void
    {
        $owner = $this->createOwner();
        [$business, $venue] = $this->createVenue($owner, Business::STATUS_ACTIVE, Venue::STATUS_PUBLISHED);

        $venue->prices()->create([
            'pricing_unit' => VenuePrice::UNIT_HOURLY,
            'step_interval_minutes' => 60,
            'min_duration_units' => 1,
            'max_duration_units' => 12,
            'price_amount' => 250000,
            'weekend_price_amount' => 300000,
            'is_active' => true,
        ]);

        Livewire::test('pages::business', ['businessSlug' => $business->slug])
            ->assertSee('Rp 250.000 / jam')
            ->assertSee('Akhir pekan Rp 300.000');
    }

    private function createOwner(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_OWNER,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    private function createVenue(
        User $owner,
        string $businessStatus = Business::STATUS_DRAFT,
        string $venueStatus = Venue::STATUS_DRAFT,
    ): array {
        $category = Category::query()->where('slug', 'meeting')->firstOrFail();
        $template = VenueTemplate::query()
            ->where('category_id', $category->getKey())
            ->where('is_active', true)
            ->firstOrFail();

        $business = $owner->businesses()->create([
            'name' => 'Studio Operasional Uji',
            'slug' => 'studio-operasional-' . strtolower($businessStatus) . '-' . strtolower($venueStatus),
            'address' => 'Jalan Contoh Nomor 10',
            'status' => $businessStatus,
            'published_at' => $businessStatus === Business::STATUS_ACTIVE ? now() : null,
        ]);

        $venue = $business->venues()->create([
            'category_id' => $category->getKey(),
            'venue_template_id' => $template->getKey(),
            'name' => 'Ruang Operasional Uji',
            'slug' => 'ruang-operasional-uji',
            'description' => 'Venue untuk menguji jadwal dan harga.',
            'status' => $venueStatus,
            'published_at' => $venueStatus === Venue::STATUS_PUBLISHED ? now() : null,
        ]);

        return [$business, $venue, $template];
    }

    private function validTemplateValues(): array
    {
        return [
            'projector_available' => true,
            'whiteboard_available' => false,
            'meeting_layout' => 'Classroom',
        ];
    }
}
