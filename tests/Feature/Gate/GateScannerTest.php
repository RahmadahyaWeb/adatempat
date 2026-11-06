<?php

namespace Tests\Feature\Gate;

use App\Enums\BookingStatus;
use App\Enums\CancellationPolicy;
use App\Enums\PaymentMethod;
use App\Enums\SpaceCategory;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Business;
use App\Models\Space;
use App\Models\User;
use App\Models\Venue;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class GateScannerTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Business $business;

    private Venue $venue;

    private Space $space;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create([
            'role' => UserRole::Owner,
            'name' => 'Pak Owner',
            'email' => 'owner@adatempat.test',
        ]);

        $this->business = Business::factory()->create([
            'user_id' => $this->owner->id,
            'name' => 'Bisnis Olahraga Prima',
        ]);

        $this->venue = Venue::factory()->approved()->create([
            'business_id' => $this->business->id,
            'name' => 'Gor Prima Jakarta',
            'city' => 'Jakarta Selatan',
            'timezone' => 'Asia/Jakarta',
            'is_published' => true,
        ]);

        $this->space = Space::factory()->create([
            'venue_id' => $this->venue->id,
            'name' => 'Lapangan Utama',
            'category' => SpaceCategory::Futsal,
            'base_price' => 150000,
            'cancellation_policy' => CancellationPolicy::FreeCancellation,
            'cancellation_cutoff_hours' => 6,
            'refund_percentage' => 100,
            'is_active' => true,
        ]);
    }

    private function createConfirmedBooking(
        string $date,
        string $startTime = '10:00:00',
        string $endTime = '12:00:00',
        ?Venue $venue = null,
        ?Space $space = null,
    ): Booking {
        $v = $venue ?? $this->venue;
        $s = $space ?? $this->space;
        $customer = User::factory()->create(['role' => UserRole::Customer]);

        return Booking::create([
            'venue_id' => $v->id,
            'space_id' => $s->id,
            'user_id' => $customer->id,
            'code' => 'ADT-BK-'.date('Ymd').'-'.strtoupper(bin2hex(random_bytes(3))),
            'booking_date' => $date,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'customer_name' => 'Budi Santoso',
            'customer_phone' => '081234567890',
            'customer_email' => 'budi@example.com',
            'subtotal_amount' => 300000,
            'addons_amount' => 0,
            'total_amount' => 300000,
            'status' => BookingStatus::Confirmed,
            'payment_method' => PaymentMethod::Qris,
            'qr_checkin_token' => bin2hex(random_bytes(32)), // 64 chars
            'expires_at' => null,
            'cancellation_policy_snapshot' => [
                'policy' => 'free_cancellation',
                'cutoff_hours' => 6,
                'refund_percentage' => 100,
            ],
        ]);
    }

    public function test_unauthenticated_user_cannot_access_scanner(): void
    {
        $response = $this->get(route('gate.scanner'));

        $response->assertRedirect(route('login'));
    }

    public function test_customer_role_cannot_access_scanner(): void
    {
        $customer = User::factory()->create(['role' => UserRole::Customer]);

        $response = $this->actingAs($customer)->get(route('gate.scanner'));

        $response->assertStatus(403);
    }

    public function test_owner_can_access_scanner_and_views_their_venues(): void
    {
        $response = $this->actingAs($this->owner)->get(route('gate.scanner'));

        $response->assertOk();
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('gate/scanner')
                ->has('venues', 1)
                ->where('venues.0.id', $this->venue->id)
                ->where('venues.0.name', $this->venue->name)
                ->where('selected_venue_id', $this->venue->id)
        );
    }

    public function test_superadmin_can_access_scanner_and_sees_all_venues(): void
    {
        $superadmin = User::factory()->create(['role' => UserRole::Superadmin]);

        // Create another venue for another business
        $otherBusiness = Business::factory()->create();
        Venue::factory()->approved()->create([
            'business_id' => $otherBusiness->id,
            'name' => 'Venue Bali',
        ]);

        $response = $this->actingAs($superadmin)->get(route('gate.scanner'));

        $response->assertOk();
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('gate/scanner')
                ->has('venues', 2)
        );
    }

    public function test_valid_checkin_by_64_char_qr_token_within_allowed_window(): void
    {
        $targetDate = CarbonImmutable::now('Asia/Jakarta')->format('Y-m-d');
        $booking = $this->createConfirmedBooking(
            date: $targetDate,
            startTime: '10:00:00',
            endTime: '12:00:00',
        );

        // Scan at 09:45 (15 mins prior to start, inside 30-min window)
        $scanTime = CarbonImmutable::parse("{$targetDate} 09:45:00", 'Asia/Jakarta');
        CarbonImmutable::setTestNow($scanTime);

        $response = $this->actingAs($this->owner)
            ->postJson(route('gate.scanner.scan'), [
                'venue_id' => $this->venue->id,
                'token' => $booking->qr_checkin_token,
            ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'booking' => [
                'code' => $booking->code,
                'customer_name' => 'Budi Santoso',
                'space_name' => 'Lapangan Utama',
            ],
        ]);

        $booking->refresh();
        $this->assertSame(BookingStatus::CheckedIn, $booking->status);
        $this->assertNotNull($booking->checked_in_at);

        CarbonImmutable::setTestNow(null);
    }

    public function test_valid_checkin_by_booking_code(): void
    {
        $targetDate = CarbonImmutable::now('Asia/Jakarta')->format('Y-m-d');
        $booking = $this->createConfirmedBooking(
            date: $targetDate,
            startTime: '14:00:00',
            endTime: '16:00:00',
        );

        // Scan at 14:10 (within slot duration)
        $scanTime = CarbonImmutable::parse("{$targetDate} 14:10:00", 'Asia/Jakarta');
        CarbonImmutable::setTestNow($scanTime);

        $response = $this->actingAs($this->owner)
            ->postJson(route('gate.scanner.scan'), [
                'venue_id' => $this->venue->id,
                'token' => $booking->code,
            ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'booking' => [
                'code' => $booking->code,
            ],
        ]);

        $booking->refresh();
        $this->assertSame(BookingStatus::CheckedIn, $booking->status);

        CarbonImmutable::setTestNow(null);
    }

    public function test_scan_fails_if_booking_already_checked_in_single_use(): void
    {
        $targetDate = CarbonImmutable::now('Asia/Jakarta')->format('Y-m-d');
        $booking = $this->createConfirmedBooking(
            date: $targetDate,
            startTime: '10:00:00',
            endTime: '12:00:00',
        );

        $scanTime = CarbonImmutable::parse("{$targetDate} 09:50:00", 'Asia/Jakarta');
        CarbonImmutable::setTestNow($scanTime);

        // First scan succeeds
        $first = $this->actingAs($this->owner)
            ->postJson(route('gate.scanner.scan'), [
                'venue_id' => $this->venue->id,
                'token' => $booking->qr_checkin_token,
            ]);
        $first->assertOk();

        // Second scan fails: single-use guarantee
        $second = $this->actingAs($this->owner)
            ->postJson(route('gate.scanner.scan'), [
                'venue_id' => $this->venue->id,
                'token' => $booking->qr_checkin_token,
            ]);

        $second->assertStatus(422);
        $second->assertJson([
            'success' => false,
            'error' => 'ALREADY_CHECKED_IN',
        ]);

        CarbonImmutable::setTestNow(null);
    }

    public function test_scan_fails_if_booking_belongs_to_different_venue(): void
    {
        // Another venue
        $otherBusiness = Business::factory()->create();
        $otherVenue = Venue::factory()->approved()->create([
            'business_id' => $otherBusiness->id,
            'name' => 'Gor Futsal Barat',
            'timezone' => 'Asia/Jakarta',
        ]);
        $otherSpace = Space::factory()->create([
            'venue_id' => $otherVenue->id,
            'name' => 'Lapangan B',
        ]);

        $targetDate = CarbonImmutable::now('Asia/Jakarta')->format('Y-m-d');
        $booking = $this->createConfirmedBooking(
            date: $targetDate,
            startTime: '10:00:00',
            endTime: '12:00:00',
            venue: $otherVenue,
            space: $otherSpace,
        );

        $scanTime = CarbonImmutable::parse("{$targetDate} 09:45:00", 'Asia/Jakarta');
        CarbonImmutable::setTestNow($scanTime);

        // Owner of venue A scans booking of venue B
        $response = $this->actingAs($this->owner)
            ->postJson(route('gate.scanner.scan'), [
                'venue_id' => $this->venue->id,
                'token' => $booking->qr_checkin_token,
            ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'error' => 'WRONG_VENUE',
        ]);

        CarbonImmutable::setTestNow(null);
    }

    public function test_scan_fails_if_outside_allowed_window_too_early(): void
    {
        $targetDate = CarbonImmutable::now('Asia/Jakarta')->format('Y-m-d');
        $booking = $this->createConfirmedBooking(
            date: $targetDate,
            startTime: '10:00:00',
            endTime: '12:00:00',
        );

        // Scan at 09:15 (45 mins before start, window opens at 09:30)
        $scanTime = CarbonImmutable::parse("{$targetDate} 09:15:00", 'Asia/Jakarta');
        CarbonImmutable::setTestNow($scanTime);

        $response = $this->actingAs($this->owner)
            ->postJson(route('gate.scanner.scan'), [
                'venue_id' => $this->venue->id,
                'token' => $booking->qr_checkin_token,
            ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'error' => 'TOO_EARLY',
        ]);

        CarbonImmutable::setTestNow(null);
    }

    public function test_scan_fails_if_outside_allowed_window_too_late(): void
    {
        $targetDate = CarbonImmutable::now('Asia/Jakarta')->format('Y-m-d');
        $booking = $this->createConfirmedBooking(
            date: $targetDate,
            startTime: '10:00:00',
            endTime: '12:00:00',
        );

        // Scan at 12:05 (5 mins past slot end)
        $scanTime = CarbonImmutable::parse("{$targetDate} 12:05:00", 'Asia/Jakarta');
        CarbonImmutable::setTestNow($scanTime);

        $response = $this->actingAs($this->owner)
            ->postJson(route('gate.scanner.scan'), [
                'venue_id' => $this->venue->id,
                'token' => $booking->qr_checkin_token,
            ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'error' => 'TOO_LATE',
        ]);

        CarbonImmutable::setTestNow(null);
    }

    public function test_scan_fails_if_booking_not_confirmed(): void
    {
        $targetDate = CarbonImmutable::now('Asia/Jakarta')->format('Y-m-d');
        $booking = $this->createConfirmedBooking(
            date: $targetDate,
            startTime: '10:00:00',
            endTime: '12:00:00',
        );
        $booking->status = BookingStatus::PendingPayment;
        $booking->save();

        $scanTime = CarbonImmutable::parse("{$targetDate} 09:45:00", 'Asia/Jakarta');
        CarbonImmutable::setTestNow($scanTime);

        $response = $this->actingAs($this->owner)
            ->postJson(route('gate.scanner.scan'), [
                'venue_id' => $this->venue->id,
                'token' => $booking->qr_checkin_token,
            ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'error' => 'NOT_CONFIRMED',
        ]);

        CarbonImmutable::setTestNow(null);
    }

    public function test_scan_fails_if_token_not_found(): void
    {
        $response = $this->actingAs($this->owner)
            ->postJson(route('gate.scanner.scan'), [
                'venue_id' => $this->venue->id,
                'token' => 'ADT-TOKEN-TIDAK-VALID-12345',
            ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'error' => 'INVALID_TOKEN',
        ]);
    }

    public function test_owner_cannot_scan_for_venue_they_do_not_own(): void
    {
        $otherBusiness = Business::factory()->create();
        $otherVenue = Venue::factory()->approved()->create([
            'business_id' => $otherBusiness->id,
            'name' => 'Gor Lain',
        ]);

        $response = $this->actingAs($this->owner)
            ->postJson(route('gate.scanner.scan'), [
                'venue_id' => $otherVenue->id,
                'token' => 'dummy-token',
            ]);

        $response->assertStatus(403);
    }
}
