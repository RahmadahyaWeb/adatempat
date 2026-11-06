<?php

namespace Tests\Feature\Bookings;

use App\Enums\BookingStatus;
use App\Enums\CancellationPolicy;
use App\Enums\NotificationChannel;
use App\Enums\NotificationStatus;
use App\Enums\SpaceCategory;
use App\Mail\BookingConfirmedMail;
use App\Models\Booking;
use App\Models\Business;
use App\Models\Schedule;
use App\Models\Space;
use App\Models\SpaceScheduleVersion;
use App\Models\User;
use App\Models\Venue;
use App\Services\Bookings\BookingService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TicketAndNotificationTest extends TestCase
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

    protected function createPendingBooking(): Booking
    {
        $targetDate = CarbonImmutable::now('Asia/Jakarta')->addDays(2)->toDateString();
        $bookingService = app(BookingService::class);

        return $bookingService->createBooking(
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
    }

    public function test_booking_confirmation_dispatches_notification_job_and_logs_sent_status(): void
    {
        Mail::fake();

        $booking = $this->createPendingBooking();
        $bookingService = app(BookingService::class);

        $confirmed = $bookingService->confirmBooking($booking);

        $this->assertTrue($confirmed);
        $this->assertSame(BookingStatus::Confirmed, $booking->status);

        // Verify notification log created and sent
        $this->assertDatabaseHas('notification_logs', [
            'booking_id' => $booking->id,
            'channel' => NotificationChannel::Email->value,
            'recipient' => 'budi@example.com',
            'template' => 'booking_confirmed',
            'status' => NotificationStatus::Sent->value,
        ]);

        // Verify mail sent with signed ticket URL
        Mail::assertSent(BookingConfirmedMail::class, function (BookingConfirmedMail $mail) use ($booking) {
            return $mail->hasTo('budi@example.com')
                && $mail->booking->id === $booking->id
                && str_contains($mail->signedTicketUrl, "/tickets/{$booking->code}")
                && str_contains($mail->signedTicketUrl, 'signature=');
        });
    }

    public function test_signed_ticket_url_allows_guest_access_with_noindex_header(): void
    {
        $booking = $this->createPendingBooking();
        app(BookingService::class)->confirmBooking($booking);

        $signedUrl = $booking->getSignedTicketUrl();

        // Guest accesses the signed URL (e.g. from customer email)
        $response = $this->get($signedUrl);

        $response->assertOk();
        $response->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('public/ticket')
                ->where('booking.code', $booking->code)
                ->where('booking.customer_name', 'Budi Santoso')
                ->where('booking.qr_checkin_token', $booking->qr_checkin_token)
        );
    }

    public function test_expired_or_tampered_signed_ticket_url_returns_403_for_guest(): void
    {
        $booking = $this->createPendingBooking();
        app(BookingService::class)->confirmBooking($booking);

        // 1. Tampered signature
        $tamperedUrl = route('tickets.show', [
            'code' => $booking->code,
            'expires' => now()->addDays(7)->getTimestamp(),
            'signature' => 'invalid_signature_hash_12345',
        ]);

        $response = $this->get($tamperedUrl);
        $response->assertStatus(403);

        // 2. Expired signature
        $expiredUrl = URL::temporarySignedRoute(
            'tickets.show',
            now()->subMinutes(10),
            ['code' => $booking->code]
        );

        $expiredResponse = $this->get($expiredUrl);
        $expiredResponse->assertStatus(403);
    }

    public function test_authenticated_booking_owner_can_access_ticket_without_signed_query(): void
    {
        $booking = $this->createPendingBooking();
        app(BookingService::class)->confirmBooking($booking);

        // Authenticated customer accesses their ticket directly without signature
        $response = $this->actingAs($this->user)
            ->get(route('tickets.show', ['code' => $booking->code]));

        $response->assertOk();
        $response->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_other_customer_cannot_access_ticket_without_valid_signature(): void
    {
        $booking = $this->createPendingBooking();
        app(BookingService::class)->confirmBooking($booking);

        $otherCustomer = User::factory()->create();

        // Other customer visits without signature
        $response = $this->actingAs($otherCustomer)
            ->get(route('tickets.show', ['code' => $booking->code]));

        $response->assertStatus(403);
    }

    public function test_ticket_data_contains_unique_64_char_checkin_token(): void
    {
        $booking = $this->createPendingBooking();
        app(BookingService::class)->confirmBooking($booking);

        $this->assertSame(64, strlen($booking->qr_checkin_token));

        $signedUrl = $booking->getSignedTicketUrl();
        $response = $this->get($signedUrl);

        $response->assertOk();
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('public/ticket')
                ->where('booking.qr_checkin_token', $booking->qr_checkin_token)
        );
    }
}
