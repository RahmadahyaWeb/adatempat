<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Category;
use App\Models\LegalDocument;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueAvailabilityRule;
use App\Models\VenuePrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PublicVenueBookingUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_venue_page_shows_booking_planner_and_policy(): void
    {
        [$business, $venue] = $this->publishedVenueFixture();

        Livewire::test('pages::venue', [
            'businessSlug' => $business->slug,
            'venueSlug' => $venue->slug,
        ])
            ->assertSee('Pilih jadwal')
            ->assertSee('Tentang tempat ini')
            ->assertSee('Lihat semua detail')
            ->assertSee('Kebijakan pembatalan')
            ->assertSee('Rp 250.000');
    }


    public function test_hourly_venue_exposes_only_availability_aware_start_slots_and_durations(): void
    {
        [$business, $venue] = $this->publishedVenueFixture();

        $component = Livewire::test('pages::venue', [
            'businessSlug' => $business->slug,
            'venueSlug' => $venue->slug,
        ])
            ->set('bookingDate', now('UTC')->addDay()->format('Y-m-d'));

        $slots = $component->instance()->hourlySlots();

        $this->assertNotEmpty($slots);
        $this->assertArrayHasKey('value', $slots[0]);
        $this->assertArrayHasKey('available', $slots[0]);

        $firstAvailable = collect($slots)->firstWhere('available', true);
        $this->assertNotNull($firstAvailable);

        $component->call('selectHourlyStart', $firstAvailable['value']);

        $durations = $component->instance()->durationOptions();

        $this->assertNotEmpty($durations);
        $this->assertArrayHasKey('units', $durations[0]);
        $this->assertArrayHasKey('end', $durations[0]);
    }

    public function test_booking_checkout_route_requires_customer_authentication(): void
    {
        [, $venue] = $this->publishedVenueFixture();

        $response = $this->get(route('customer.booking.checkout', [
            'venue' => $venue->public_id,
            'starts' => now('UTC')->addDay()->setTime(9, 0)->toIso8601String(),
            'ends' => now('UTC')->addDay()->setTime(10, 0)->toIso8601String(),
        ]));

        $response->assertRedirect(route('login'));
    }

    public function test_customer_checkout_renders_quote_and_legal_consent(): void
    {
        [, $venue] = $this->publishedVenueFixture();
        $customer = User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        LegalDocument::query()->create([
            'type' => 'terms_and_conditions',
            'version' => 'v1.0-test',
            'title' => 'Syarat Booking Uji',
            'content' => 'Dokumen uji.',
            'status' => 'published',
            'published_at' => now()->subMinute(),
        ]);

        LegalDocument::query()->create([
            'type' => 'privacy_policy',
            'version' => 'v1.0-test',
            'title' => 'Privasi Booking Uji',
            'content' => 'Dokumen uji.',
            'status' => 'published',
            'published_at' => now()->subMinute(),
        ]);

        $starts = now('UTC')->addDay()->setTime(9, 0)->startOfMinute();
        $ends = $starts->copy()->addHour();

        Livewire::actingAs($customer)
            ->withQueryParams([
                'starts' => $starts->toIso8601String(),
                'ends' => $ends->toIso8601String(),
                'sessions' => '',
            ])
            ->test('pages::customer.booking-checkout', ['venue' => $venue->public_id])
            ->assertSee('Periksa detail sebelum membuat booking.')
            ->assertSee('Syarat Booking Uji')
            ->assertSee('Privasi Booking Uji')
            ->assertSee('Rp 250.000');
    }

    private function publishedVenueFixture(): array
    {
        $owner = User::factory()->create([
            'role' => User::ROLE_OWNER,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $this->createEligibleSubscription($owner);

        $business = $owner->businesses()->create([
            'name' => 'Studio Booking Uji',
            'slug' => 'studio-booking-uji',
            'address' => 'Banjarbaru, Kalimantan Selatan',
            'timezone' => 'UTC',
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
            'name' => 'Ruang Booking Uji',
            'slug' => 'ruang-booking-uji',
            'description' => 'Venue untuk menguji halaman booking.',
            'capacity_persons' => 40,
            'facilities_json' => ['Wi-Fi', 'AC'],
            'status' => Venue::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        VenuePrice::query()->create([
            'venue_id' => $venue->getKey(),
            'pricing_unit' => VenuePrice::UNIT_HOURLY,
            'step_interval_minutes' => 60,
            'min_duration_units' => 1,
            'max_duration_units' => 8,
            'price_amount' => 250000,
            'weekend_price_amount' => 300000,
            'is_active' => true,
        ]);

        for ($day = 0; $day <= 6; $day++) {
            VenueAvailabilityRule::query()->create([
                'venue_id' => $venue->getKey(),
                'day_of_week' => $day,
                'is_open' => true,
                'open_time' => '00:00',
                'close_time' => '23:59',
                'buffer_minutes' => 0,
            ]);
        }

        return [$business, $venue];
    }
}
