<?php

namespace Tests\Feature\Bookings;

use App\Enums\BookingStatus;
use App\Enums\CancellationPolicy;
use App\Enums\SpaceCategory;
use App\Exceptions\SlotConflictException;
use App\Models\Booking;
use App\Models\Business;
use App\Models\Schedule;
use App\Models\Space;
use App\Models\SpaceScheduleVersion;
use App\Models\User;
use App\Models\Venue;
use App\Services\Bookings\BookingService;
use App\Services\Slots\SlotGeneratorService;
use App\Services\Slots\SlotReservationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BookingHoldExpiryTest extends TestCase
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

    public function test_expires_pending_booking_and_releases_canonical_slots(): void
    {
        $targetDate = CarbonImmutable::now('Asia/Jakarta')->addDays(2)->toDateString();
        $bookingService = app(BookingService::class);
        $slotGenerator = app(SlotGeneratorService::class);

        $booking = $bookingService->createBooking(
            user: $this->user,
            space: $this->space,
            date: $targetDate,
            slotTimes: ['10:00', '11:00'],
            addOnSelections: [],
            customerData: [
                'customer_name' => 'Budi Santoso',
                'customer_phone' => '081234567890',
                'customer_email' => 'budi@example.com',
            ],
        );

        $this->assertSame(BookingStatus::PendingPayment, $booking->status);
        $this->assertDatabaseCount('booking_slots', 2);

        // Manually simulate 10-minute hold expiration
        $booking->expires_at = now()->subMinutes(5);
        $booking->save();

        $expiredCount = $bookingService->expirePendingBookings();

        $this->assertSame(1, $expiredCount);
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => BookingStatus::Expired->value,
        ]);
        $this->assertDatabaseCount('booking_slots', 0);

        // Grid slots must now show 10:00 and 11:00 as available again
        $generated = $slotGenerator->generate($this->space, $targetDate);
        $slotsByTime = collect($generated['slots'])->keyBy('start_time');

        $this->assertSame('available', $slotsByTime['10:00']['status']);
        $this->assertSame('available', $slotsByTime['11:00']['status']);
    }

    public function test_does_not_expire_confirmed_or_active_bookings(): void
    {
        $targetDate = CarbonImmutable::now('Asia/Jakarta')->addDays(2)->toDateString();
        $bookingService = app(BookingService::class);

        $booking = $bookingService->createBooking(
            user: $this->user,
            space: $this->space,
            date: $targetDate,
            slotTimes: ['10:00'],
            addOnSelections: [],
            customerData: [
                'customer_name' => 'Budi Santoso',
                'customer_phone' => '081234567890',
                'customer_email' => 'budi@example.com',
            ],
        );

        // Transition booking to Confirmed
        $booking->status = BookingStatus::Confirmed;
        $booking->expires_at = now()->subMinutes(10);
        $booking->save();

        $expiredCount = $bookingService->expirePendingBookings();

        $this->assertSame(0, $expiredCount);
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => BookingStatus::Confirmed->value,
        ]);
        $this->assertDatabaseCount('booking_slots', 1);
    }

    public function test_artisan_command_expires_pending_bookings(): void
    {
        $targetDate = CarbonImmutable::now('Asia/Jakarta')->addDays(2)->toDateString();
        $bookingService = app(BookingService::class);

        $booking = $bookingService->createBooking(
            user: $this->user,
            space: $this->space,
            date: $targetDate,
            slotTimes: ['14:00'],
            addOnSelections: [],
            customerData: [
                'customer_name' => 'Budi Santoso',
                'customer_phone' => '081234567890',
                'customer_email' => 'budi@example.com',
            ],
        );

        $booking->expires_at = now()->subMinutes(1);
        $booking->save();

        $this->artisan('bookings:expire-pending')
            ->expectsOutputToContain('Expired 1 pending booking(s)')
            ->assertSuccessful();

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => BookingStatus::Expired->value,
        ]);
        $this->assertDatabaseCount('booking_slots', 0);
    }

    public function test_payment_page_automatically_expires_past_due_booking_on_visit(): void
    {
        $targetDate = CarbonImmutable::now('Asia/Jakarta')->addDays(2)->toDateString();
        $bookingService = app(BookingService::class);

        $booking = $bookingService->createBooking(
            user: $this->user,
            space: $this->space,
            date: $targetDate,
            slotTimes: ['16:00'],
            addOnSelections: [],
            customerData: [
                'customer_name' => 'Budi Santoso',
                'customer_phone' => '081234567890',
                'customer_email' => 'budi@example.com',
            ],
        );

        $booking->expires_at = now()->subMinutes(2);
        $booking->save();

        $response = $this->actingAs($this->user)->get(route('payment.show', ['code' => $booking->code]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('public/payment-pending')
            ->where('booking.status', 'expired')
            ->where('booking.status_label', 'Kedaluwarsa')
        );

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => BookingStatus::Expired->value,
        ]);
        $this->assertDatabaseCount('booking_slots', 0);
    }

    public function test_all_or_nothing_rollback_leaves_no_partial_slots_on_conflict(): void
    {
        $targetDate = CarbonImmutable::now('Asia/Jakarta')->addDays(2)->toDateString();
        $reservationService = app(SlotReservationService::class);

        // Initial booking occupies 10:00 cell
        $reservationService->reserve(
            bookingId: 101,
            spaceId: $this->space->id,
            slotDate: $targetDate,
            cells: [
                ['start_time' => '10:00', 'end_time' => '11:00', 'price' => 150000],
            ],
        );

        $this->assertDatabaseCount('booking_slots', 1);

        // Second booking attempts 10:00 (conflicts) and 11:00 (free)
        try {
            $reservationService->reserve(
                bookingId: 102,
                spaceId: $this->space->id,
                slotDate: $targetDate,
                cells: [
                    ['start_time' => '10:00', 'end_time' => '11:00', 'price' => 150000],
                    ['start_time' => '11:00', 'end_time' => '12:00', 'price' => 150000],
                ],
            );
            $this->fail('Expected SlotConflictException was not thrown.');
        } catch (SlotConflictException $e) {
            $this->assertStringContainsString('Slot sudah dipesan', $e->getMessage());
        }

        // Verify all-or-nothing rollback: 11:00 was not persisted for booking 102
        $this->assertDatabaseMissing('booking_slots', ['booking_id' => 102]);
        $this->assertDatabaseCount('booking_slots', 1);
    }
}
