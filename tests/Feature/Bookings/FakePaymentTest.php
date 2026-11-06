<?php

namespace Tests\Feature\Bookings;

use App\Contracts\Payments\PaymentGatewayInterface;
use App\Enums\BookingStatus;
use App\Enums\CancellationPolicy;
use App\Enums\SpaceCategory;
use App\Models\Booking;
use App\Models\Business;
use App\Models\Schedule;
use App\Models\Space;
use App\Models\SpaceScheduleVersion;
use App\Models\User;
use App\Models\Venue;
use App\Services\Bookings\BookingService;
use App\Services\Slots\SlotGeneratorService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FakePaymentTest extends TestCase
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

    protected function createPendingBooking(array $slotTimes = ['10:00', '11:00'], ?string $date = null): Booking
    {
        $targetDate = $date ?? CarbonImmutable::now('Asia/Jakarta')->addDays(2)->toDateString();
        $bookingService = app(BookingService::class);

        return $bookingService->createBooking(
            user: $this->user,
            space: $this->space,
            date: $targetDate,
            slotTimes: $slotTimes,
            addOnSelections: [],
            customerData: [
                'customer_name' => 'Budi Santoso',
                'customer_phone' => '081234567890',
                'customer_email' => 'budi@example.com',
            ],
        );
    }

    public function test_fake_payment_confirms_pending_booking_and_preserves_slots(): void
    {
        $targetDate = CarbonImmutable::now('Asia/Jakarta')->addDays(2)->toDateString();
        $booking = $this->createPendingBooking(date: $targetDate);

        $this->assertSame(BookingStatus::PendingPayment, $booking->status);
        $this->assertDatabaseCount('booking_slots', 2);

        $response = $this->actingAs($this->user)
            ->post(route('payment.simulate', ['code' => $booking->code]));

        $response->assertRedirect();
        $booking->refresh();

        $this->assertSame(BookingStatus::Confirmed, $booking->status);
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => BookingStatus::Confirmed->value,
        ]);

        // Retain booking slots permanently
        $this->assertDatabaseCount('booking_slots', 2);

        // Slots in grid must remain 'booked'
        $slotGenerator = app(SlotGeneratorService::class);
        $generated = $slotGenerator->generate($this->space, $targetDate);
        $slotsByTime = collect($generated['slots'])->keyBy('start_time');

        $this->assertSame('booked', $slotsByTime['10:00']['status']);
        $this->assertSame('booked', $slotsByTime['11:00']['status']);
    }

    public function test_cannot_confirm_past_due_or_expired_booking(): void
    {
        $booking = $this->createPendingBooking();

        // Simulate 10-minute hold expiration
        $booking->expires_at = now()->subMinutes(5);
        $booking->save();

        $response = $this->actingAs($this->user)
            ->postJson(route('payment.simulate', ['code' => $booking->code]));

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'status' => BookingStatus::Expired->value,
            ]);

        $booking->refresh();
        $this->assertSame(BookingStatus::Expired, $booking->status);
        $this->assertDatabaseCount('booking_slots', 0);
    }

    public function test_payment_status_endpoint_returns_json_and_reflects_gateway_status(): void
    {
        $booking = $this->createPendingBooking();

        // 1. Initially pending
        $response = $this->actingAs($this->user)
            ->getJson(route('payment.status', ['code' => $booking->code]));

        $response->assertOk()
            ->assertJson([
                'code' => $booking->code,
                'status' => 'pending_payment',
                'status_label' => 'Menunggu Pembayaran',
                'is_confirmed' => false,
                'is_expired' => false,
            ]);

        // 2. Gateway status updated externally to PAID
        $gateway = app(PaymentGatewayInterface::class);
        $gateway->simulatePaymentSuccess($booking->code);

        // 3. Polling endpoint detects PAID gateway and confirms booking
        $pollResponse = $this->actingAs($this->user)
            ->getJson(route('payment.status', ['code' => $booking->code]));

        $pollResponse->assertOk()
            ->assertJson([
                'code' => $booking->code,
                'status' => 'confirmed',
                'status_label' => 'Terkonfirmasi',
                'is_confirmed' => true,
                'is_expired' => false,
            ]);

        $booking->refresh();
        $this->assertSame(BookingStatus::Confirmed, $booking->status);
    }

    public function test_simulate_endpoint_disabled_in_production(): void
    {
        $booking = $this->createPendingBooking();

        $this->app->detectEnvironment(fn () => 'production');
        $response = $this->actingAs($this->user)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->post(route('payment.simulate', ['code' => $booking->code]));

        $response->assertStatus(403);
    }

    public function test_artisan_command_fake_pay_confirms_booking(): void
    {
        $booking = $this->createPendingBooking();

        $this->artisan('booking:fake-pay', ['code' => $booking->code])
            ->expectsOutput("Booking '{$booking->code}' berhasil dibayar dan dikonfirmasi.")
            ->assertExitCode(0);

        $booking->refresh();
        $this->assertSame(BookingStatus::Confirmed, $booking->status);

        // Idempotency: warning when already confirmed
        $this->artisan('booking:fake-pay', ['code' => $booking->code])
            ->expectsOutput("Booking '{$booking->code}' sudah terkonfirmasi sebelumnya.")
            ->assertExitCode(0);

        // Unknown code fails
        $this->artisan('booking:fake-pay', ['code' => 'ADT-BK-99999999-NOTFOUND'])
            ->expectsOutput("Booking dengan kode 'ADT-BK-99999999-NOTFOUND' tidak ditemukan.")
            ->assertExitCode(1);
    }
}
