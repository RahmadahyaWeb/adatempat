<?php

namespace Tests\Feature\Bookings;

use App\Enums\AddOnPricingType;
use App\Enums\CancellationPolicy;
use App\Enums\SpaceCategory;
use App\Models\Business;
use App\Models\Schedule;
use App\Models\Space;
use App\Models\SpaceAddOn;
use App\Models\SpaceScheduleVersion;
use App\Models\User;
use App\Models\Venue;
use App\Services\Slots\SlotReservationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CheckoutPageTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Venue $venue;

    private Space $space;

    private SpaceScheduleVersion $version;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'name' => 'Budi Santoso',
            'email' => 'budi@example.com',
        ]);

        $owner = User::factory()->create();
        $business = Business::factory()->create(['user_id' => $owner->id]);

        $this->venue = Venue::factory()->approved()->create([
            'business_id' => $business->id,
            'name' => 'Gor Futsal Prima',
            'city' => 'Jakarta Selatan',
            'timezone' => 'Asia/Jakarta',
            'is_published' => true,
        ]);

        $this->space = Space::factory()->create([
            'venue_id' => $this->venue->id,
            'name' => 'Lapangan A',
            'category' => SpaceCategory::Futsal,
            'base_price' => 150000,
            'cancellation_policy' => CancellationPolicy::FreeCancellation,
            'cancellation_cutoff_hours' => 6,
            'refund_percentage' => 100,
            'is_active' => true,
        ]);

        $this->version = SpaceScheduleVersion::factory()->create([
            'space_id' => $this->space->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'effective_to' => null,
            'created_by' => $owner->id,
        ]);

        // Schedules for every day (0..6)
        for ($day = 0; $day <= 6; $day++) {
            Schedule::factory()->create([
                'space_id' => $this->space->id,
                'schedule_version_id' => $this->version->id,
                'day_of_week' => $day,
                'open_time' => '08:00:00',
                'close_time' => '22:00:00',
                'slot_duration_minutes' => 60,
                'is_active' => true,
            ]);
        }
    }

    public function test_guest_redirected_to_login_when_accessing_checkout(): void
    {
        $targetDate = CarbonImmutable::now('Asia/Jakarta')->addDays(2)->toDateString();

        $response = $this->get(route('checkout.create', [
            'space' => $this->space->slug,
            'date' => $targetDate,
            'slots' => '10:00,11:00',
        ]));

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_customer_can_view_checkout_page(): void
    {
        $targetDate = CarbonImmutable::now('Asia/Jakarta')->addDays(2)->toDateString();

        $response = $this->actingAs($this->user)->get(route('checkout.create', [
            'space' => $this->space->slug,
            'date' => $targetDate,
            'slots' => '10:00,11:00',
        ]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('public/checkout')
            ->has('space')
            ->where('space.id', $this->space->id)
            ->where('space.name', 'Lapangan A')
            ->has('venue')
            ->where('venue.id', $this->venue->id)
            ->where('date', $targetDate)
            ->has('slots', 2)
            ->where('customer.name', 'Budi Santoso')
            ->where('customer.email', 'budi@example.com')
            ->has('cancellationPolicy')
            ->where('initialPricing.slot_subtotal', 300000)
            ->where('initialPricing.duration_hours', 2)
        );
    }

    public function test_checkout_redirects_if_query_params_missing(): void
    {
        $response = $this->actingAs($this->user)->get(route('checkout.create'));
        $response->assertRedirect(route('spaces.index'));
    }

    public function test_checkout_redirects_if_requested_slot_is_already_booked(): void
    {
        $targetDate = CarbonImmutable::now('Asia/Jakarta')->addDays(3)->toDateString();

        // Reserve 10:00 - 11:00 slot first
        (new SlotReservationService)->reserve(
            bookingId: 999,
            spaceId: $this->space->id,
            slotDate: $targetDate,
            cells: [
                ['start_time' => '10:00:00', 'end_time' => '11:00:00', 'price' => 150000],
            ],
        );

        $response = $this->actingAs($this->user)->get(route('checkout.create', [
            'space' => $this->space->slug,
            'date' => $targetDate,
            'slots' => '10:00,11:00',
        ]));

        $response->assertRedirect(route('spaces.show', [
            'space' => $this->space->slug,
            'date' => $targetDate,
        ]));
        $response->assertSessionHas('error');
    }

    public function test_checkout_displays_available_add_ons(): void
    {
        $targetDate = CarbonImmutable::now('Asia/Jakarta')->addDays(2)->toDateString();

        SpaceAddOn::factory()->create([
            'space_id' => $this->space->id,
            'name' => 'Rompi Latihan',
            'price' => 20000,
            'pricing_type' => AddOnPricingType::PerBooking,
            'is_active' => true,
        ]);

        SpaceAddOn::factory()->create([
            'space_id' => $this->space->id,
            'name' => 'Bola Futsal Extra',
            'price' => 15000,
            'pricing_type' => AddOnPricingType::PerHour,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)->get(route('checkout.create', [
            'space' => $this->space->slug,
            'date' => $targetDate,
            'slots' => '14:00',
        ]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('public/checkout')
            ->has('addOns', 2)
            ->where('addOns.0.name', 'Bola Futsal Extra')
            ->where('addOns.1.name', 'Rompi Latihan')
        );
    }

    public function test_checkout_returns_404_if_space_inactive(): void
    {
        $this->space->update(['is_active' => false]);
        $targetDate = CarbonImmutable::now('Asia/Jakarta')->addDays(2)->toDateString();

        $response = $this->actingAs($this->user)->get(route('checkout.create', [
            'space' => $this->space->slug,
            'date' => $targetDate,
            'slots' => '10:00',
        ]));

        $response->assertNotFound();
    }

    public function test_checkout_conflict_redirects_back_with_slots_error(): void
    {
        $targetDate = CarbonImmutable::now('Asia/Jakarta')->addDays(2)->toDateString();

        // First user reserves 10:00
        app(SlotReservationService::class)->reserve(
            bookingId: 888,
            spaceId: $this->space->id,
            slotDate: $targetDate,
            cells: [['start_time' => '10:00', 'end_time' => '11:00']],
        );

        // Second user attempts to checkout for 10:00
        $response = $this->actingAs($this->user)->post(route('checkout.store'), [
            'space_id' => $this->space->id,
            'date' => $targetDate,
            'slots' => ['10:00'],
            'customer_name' => 'Budi Santoso',
            'customer_phone' => '081234567890',
            'customer_email' => 'budi@example.com',
        ]);

        $response->assertSessionHasErrors(['slots']);
    }
}
