<?php

namespace Tests\Feature\Bookings;

use App\Enums\AddOnPricingType;
use App\Enums\BookingStatus;
use App\Enums\CancellationPolicy;
use App\Enums\PaymentMethod;
use App\Enums\SpaceCategory;
use App\Models\Booking;
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
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CreateBookingTest extends TestCase
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
            'name' => 'Ahmad Reza',
            'email' => 'ahmad@example.com',
        ]);

        $owner = User::factory()->create();
        $business = Business::factory()->create(['user_id' => $owner->id]);

        $this->venue = Venue::factory()->approved()->create([
            'business_id' => $business->id,
            'name' => 'Center Court Futsal',
            'city' => 'Bandung',
            'timezone' => 'Asia/Jakarta',
            'is_published' => true,
        ]);

        $this->space = Space::factory()->create([
            'venue_id' => $this->venue->id,
            'name' => 'Lapangan Vinyl 1',
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

        for ($day = 0; $day <= 6; $day++) {
            Schedule::factory()->create([
                'space_id' => $this->space->id,
                'schedule_version_id' => $this->version->id,
                'day_of_week' => $day,
                'open_time' => '07:00:00',
                'close_time' => '23:00:00',
                'slot_duration_minutes' => 60,
                'is_active' => true,
            ]);
        }
    }

    public function test_can_create_booking_with_pending_payment_status(): void
    {
        $targetDate = CarbonImmutable::now('Asia/Jakarta')->addDays(2)->toDateString();

        $response = $this->actingAs($this->user)->post(route('checkout.store'), [
            'space_id' => $this->space->id,
            'date' => $targetDate,
            'slots' => ['18:00', '19:00'],
            'customer_name' => 'Ahmad Reza',
            'customer_phone' => '081234567890',
            'customer_email' => 'ahmad@example.com',
            'notes' => 'Tolong sediakan air mineral.',
        ]);

        $booking = Booking::query()->first();

        $this->assertNotNull($booking);
        $response->assertRedirect(route('payment.show', ['code' => $booking->code]));

        $this->assertSame(BookingStatus::PendingPayment, $booking->status);
        $this->assertSame(PaymentMethod::Qris, $booking->payment_method);
        $this->assertSame('18:00:00', $booking->start_time);
        $this->assertSame('20:00:00', $booking->end_time);
        $this->assertSame('300000.00', (string) $booking->subtotal_amount);
        $this->assertSame('0.00', (string) $booking->addons_amount);
        $this->assertSame('300000.00', (string) $booking->total_amount);
        $this->assertStringStartsWith('ADT-BK-', $booking->code);
        $this->assertSame(64, strlen($booking->qr_checkin_token));
        $this->assertNotNull($booking->expires_at);
        $this->assertTrue($booking->expires_at->gt(now()));
        $this->assertSame('Tolong sediakan air mineral.', $booking->notes);
    }

    public function test_calculates_correct_totals_for_per_hour_per_item_per_booking_addons(): void
    {
        $targetDate = CarbonImmutable::now('Asia/Jakarta')->addDays(3)->toDateString();

        $addonPerHour = SpaceAddOn::factory()->create([
            'space_id' => $this->space->id,
            'name' => 'Pelatih Futsal',
            'price' => 50000,
            'pricing_type' => AddOnPricingType::PerHour,
            'is_active' => true,
        ]);

        $addonPerItem = SpaceAddOn::factory()->create([
            'space_id' => $this->space->id,
            'name' => 'Sewa Rompi Tim',
            'price' => 20000,
            'pricing_type' => AddOnPricingType::PerItem,
            'stock_quantity' => 10,
            'is_active' => true,
        ]);

        $addonPerBooking = SpaceAddOn::factory()->create([
            'space_id' => $this->space->id,
            'name' => 'Dokumentasi Foto',
            'price' => 100000,
            'pricing_type' => AddOnPricingType::PerBooking,
            'is_active' => true,
        ]);

        // 2 slots = 2 hours: 19:00 - 21:00 (subtotal 300,000)
        // Add-on 1: 50,000 * 2 hours * 1 qty = 100,000
        // Add-on 2: 20,000 * 2 qty = 40,000
        // Add-on 3: 100,000 (flat per booking) = 100,000
        // Addons total: 240,000
        // Total amount: 540,000
        $response = $this->actingAs($this->user)->post(route('checkout.store'), [
            'space_id' => $this->space->id,
            'date' => $targetDate,
            'slots' => ['19:00', '20:00'],
            'add_ons' => [
                ['id' => $addonPerHour->id, 'quantity' => 1],
                ['id' => $addonPerItem->id, 'quantity' => 2],
                ['id' => $addonPerBooking->id, 'quantity' => 1],
            ],
            'customer_name' => 'Ahmad Reza',
            'customer_phone' => '081234567890',
            'customer_email' => 'ahmad@example.com',
        ]);

        $booking = Booking::with('addOns')->first();
        $this->assertNotNull($booking);
        $response->assertRedirect(route('payment.show', ['code' => $booking->code]));

        $this->assertSame('300000.00', (string) $booking->subtotal_amount);
        $this->assertSame('240000.00', (string) $booking->addons_amount);
        $this->assertSame('540000.00', (string) $booking->total_amount);
        $this->assertCount(3, $booking->addOns);

        $this->assertDatabaseHas('booking_add_ons', [
            'booking_id' => $booking->id,
            'space_add_on_id' => $addonPerHour->id,
            'quantity' => 1,
            'total_price' => 100000.00,
        ]);

        $this->assertDatabaseHas('booking_add_ons', [
            'booking_id' => $booking->id,
            'space_add_on_id' => $addonPerItem->id,
            'quantity' => 2,
            'total_price' => 40000.00,
        ]);

        $this->assertDatabaseHas('booking_add_ons', [
            'booking_id' => $booking->id,
            'space_add_on_id' => $addonPerBooking->id,
            'quantity' => 1,
            'total_price' => 100000.00,
        ]);
    }

    public function test_reserves_canonical_booking_slots_in_database(): void
    {
        $targetDate = CarbonImmutable::now('Asia/Jakarta')->addDays(2)->toDateString();

        $this->actingAs($this->user)->post(route('checkout.store'), [
            'space_id' => $this->space->id,
            'date' => $targetDate,
            'slots' => ['09:00', '10:00'],
            'customer_name' => 'Ahmad Reza',
            'customer_phone' => '081234567890',
            'customer_email' => 'ahmad@example.com',
        ]);

        $booking = Booking::query()->first();

        $this->assertDatabaseHas('booking_slots', [
            'booking_id' => $booking->id,
            'space_id' => $this->space->id,
            'slot_date' => $targetDate,
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
        ]);

        $this->assertDatabaseHas('booking_slots', [
            'booking_id' => $booking->id,
            'space_id' => $this->space->id,
            'slot_date' => $targetDate,
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
        ]);
    }

    public function test_rolls_back_and_fails_if_slot_conflict_occurs(): void
    {
        $targetDate = CarbonImmutable::now('Asia/Jakarta')->addDays(2)->toDateString();

        // Another user reserves the 15:00 slot first
        (new SlotReservationService)->reserve(
            bookingId: 888,
            spaceId: $this->space->id,
            slotDate: $targetDate,
            cells: [
                ['start_time' => '15:00:00', 'end_time' => '16:00:00', 'price' => 150000],
            ],
        );

        $response = $this->actingAs($this->user)->from(route('checkout.create', [
            'space' => $this->space->slug,
            'date' => $targetDate,
            'slots' => '15:00',
        ]))->post(route('checkout.store'), [
            'space_id' => $this->space->id,
            'date' => $targetDate,
            'slots' => ['15:00'],
            'customer_name' => 'Ahmad Reza',
            'customer_phone' => '081234567890',
            'customer_email' => 'ahmad@example.com',
        ]);

        $response->assertSessionHasErrors('slots');
        $this->assertDatabaseMissing('bookings', [
            'user_id' => $this->user->id,
        ]);
    }

    public function test_invalidates_redis_slot_cache_after_booking_creation(): void
    {
        $targetDate = CarbonImmutable::now('Asia/Jakarta')->addDays(2)->toDateString();
        $cacheKey = "slots:space:{$this->space->id}:date:{$targetDate}";

        Cache::put($cacheKey, ['dummy' => 'cached_data'], 3600);
        $this->assertTrue(Cache::has($cacheKey));

        $this->actingAs($this->user)->post(route('checkout.store'), [
            'space_id' => $this->space->id,
            'date' => $targetDate,
            'slots' => ['11:00'],
            'customer_name' => 'Ahmad Reza',
            'customer_phone' => '081234567890',
            'customer_email' => 'ahmad@example.com',
        ]);

        $this->assertFalse(Cache::has($cacheKey));
    }

    public function test_validates_customer_phone_format(): void
    {
        $targetDate = CarbonImmutable::now('Asia/Jakarta')->addDays(2)->toDateString();

        // Invalid phone
        $response = $this->actingAs($this->user)->post(route('checkout.store'), [
            'space_id' => $this->space->id,
            'date' => $targetDate,
            'slots' => ['12:00'],
            'customer_name' => 'Ahmad Reza',
            'customer_phone' => '123456',
            'customer_email' => 'ahmad@example.com',
        ]);

        $response->assertSessionHasErrors('customer_phone');

        // Valid phone with +62
        $responseValid = $this->actingAs($this->user)->post(route('checkout.store'), [
            'space_id' => $this->space->id,
            'date' => $targetDate,
            'slots' => ['12:00'],
            'customer_name' => 'Ahmad Reza',
            'customer_phone' => '+628123456789',
            'customer_email' => 'ahmad@example.com',
        ]);

        $responseValid->assertSessionHasNoErrors();
    }

    public function test_owner_can_view_payment_page_other_user_forbidden(): void
    {
        $booking = Booking::factory()->create([
            'user_id' => $this->user->id,
            'venue_id' => $this->venue->id,
            'space_id' => $this->space->id,
            'status' => BookingStatus::PendingPayment,
        ]);

        // Owner can access
        $response = $this->actingAs($this->user)->get(route('payment.show', ['code' => $booking->code]));
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('public/payment-pending')
            ->where('booking.code', $booking->code)
            ->where('booking.status', 'pending_payment')
        );

        // Other customer forbidden
        $otherUser = User::factory()->create();
        $responseForbidden = $this->actingAs($otherUser)->get(route('payment.show', ['code' => $booking->code]));
        $responseForbidden->assertForbidden();
    }
}
