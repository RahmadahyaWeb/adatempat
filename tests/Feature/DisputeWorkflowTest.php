<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Business;
use App\Models\Category;
use App\Models\Dispute;
use App\Models\LegalDocument;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\PayoutAccount;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\TermsAcceptance;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueAvailabilityRule;
use App\Services\Booking\BookingEngineService;
use App\Services\Booking\DisputeResolutionService;
use App\Services\Booking\DisputeService;
use App\Services\Payments\PaymentWebhookProcessor;
use App\Services\Payments\RefundWebhookProcessor;
use Carbon\CarbonImmutable;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DisputeWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CategorySeeder::class);
    }

    public function test_customer_can_open_dispute_within_twenty_four_hours_and_payout_is_held(): void
    {
        [$owner, $customer, $booking] = $this->createConfirmedBooking('dispute-open-001');
        $this->setBookingEndedOneHourAgo($booking);

        $dispute = app(DisputeService::class)->open(
            $customer,
            $booking->fresh(),
            'venue_condition',
            'Kondisi venue berbeda dari informasi dan fasilitas yang dijanjikan pada halaman publik.',
        );

        $this->assertSame(Dispute::STATUS_OPEN, $dispute->status);
        $this->assertSame(Booking::STATUS_DISPUTED, $booking->fresh()->status);
        $this->assertDatabaseHas('payouts', [
            'booking_id' => $booking->getKey(),
            'status' => Payout::STATUS_ON_HOLD,
        ]);
    }

    public function test_owner_response_moves_customer_dispute_under_review_and_is_audited(): void
    {
        [$owner, $customer, $booking] = $this->createConfirmedBooking('dispute-response-001');
        $this->setBookingEndedOneHourAgo($booking);
        $dispute = app(DisputeService::class)->open(
            $customer,
            $booking->fresh(),
            'service_not_delivered',
            'Venue tidak dapat digunakan sesuai booking dan tidak ada pengganti yang diberikan.',
        );

        $updated = app(DisputeService::class)->submitOwnerResponse(
            $owner,
            $dispute,
            'Saya menanggapi laporan ini. Venue tersedia pada waktu tersebut dan saya melampirkan kronologi pemeriksaan internal.',
        );

        $this->assertSame(Dispute::STATUS_UNDER_REVIEW, $updated->status);
        $this->assertNotNull($updated->owner_responded_at);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Dispute::class,
            'auditable_id' => $dispute->getKey(),
            'action' => 'booking.dispute_owner_response_submitted',
        ]);
    }

    public function test_super_admin_can_resolve_to_customer_and_create_full_refund_obligation(): void
    {
        [$owner, $customer, $booking] = $this->createConfirmedBooking('dispute-resolve-customer-001');
        $this->setBookingEndedOneHourAgo($booking);
        $dispute = app(DisputeService::class)->open(
            $customer,
            $booking->fresh(),
            'service_not_delivered',
            'Venue tidak dapat digunakan dan pemilik tidak menyediakan alternatif yang disepakati.',
        );
        $admin = $this->createSuperAdmin();

        $result = app(DisputeResolutionService::class)->resolve(
            $admin,
            $dispute,
            Dispute::STATUS_RESOLVED_TO_CUSTOMER,
            0,
            0,
            'Bukti transaksi dan dokumentasi menunjukkan layanan tidak diberikan sesuai booking pelanggan.',
        );

        $this->assertSame(Dispute::STATUS_RESOLVED_TO_CUSTOMER, $result['dispute']->status);
        $this->assertSame(100000, $result['refund']->amount);
        $this->assertSame('processing', $result['refund']->status);
        $this->assertSame(Booking::STATUS_CANCELLED_BY_ADMIN, $booking->fresh()->status);
        $this->assertSame(Payout::STATUS_CANCELLED, $result['payout']->status);
        $this->assertDatabaseHas('ledger_entries', [
            'account_code' => 'refund_payable',
            'reference_type' => 'refund',
            'reference_id' => $result['refund']->getKey(),
            'entry_type' => 'credit',
            'amount' => 100000,
        ]);
    }

    public function test_split_resolution_keeps_payout_on_hold_until_refund_succeeds(): void
    {
        [$owner, $customer, $booking] = $this->createConfirmedBooking('dispute-resolve-split-001');
        $this->setBookingEndedOneHourAgo($booking);
        $dispute = app(DisputeService::class)->open(
            $customer,
            $booking->fresh(),
            'venue_condition',
            'Kondisi venue menyebabkan sebagian penggunaan tidak dapat dilakukan sesuai kesepakatan.',
        );
        $admin = $this->createSuperAdmin();
        $account = PayoutAccount::query()->create([
            'owner_id' => $owner->getKey(),
            'bank_code' => 'BCA',
            'bank_name' => 'Bank Central Asia',
            'account_number_encrypted' => '123456789012',
            'account_number_last4' => '9012',
            'account_holder_name' => 'Pemilik Usaha',
            'verification_status' => PayoutAccount::STATUS_VERIFIED,
            'verified_by' => $admin->getKey(),
            'verified_at' => now('UTC'),
        ]);

        $result = app(DisputeResolutionService::class)->resolve(
            $admin,
            $dispute,
            Dispute::STATUS_RESOLVED_SPLIT,
            20000,
            70000,
            'Bukti menunjukkan penggunaan sebagian layanan sehingga diputuskan refund parsial dan payout sesuai nominal award.',
        );

        $this->assertSame(Payout::STATUS_ON_HOLD, $result['payout']->status);
        $this->assertSame(20000, $result['refund']->amount);

        app(RefundWebhookProcessor::class)->process('mock', [
            'event_id' => 'evt-dispute-refund-split-001',
            'gateway_refund_id' => $result['refund']->gateway_refund_id,
            'status' => 'succeeded',
        ]);

        $this->assertSame(Payout::STATUS_ELIGIBLE, $result['payout']->fresh()->status);
        $this->assertSame($account->getKey(), $result['payout']->fresh()->payout_account_id);
    }

    public function test_customer_cannot_open_dispute_after_the_twenty_four_hour_deadline(): void
    {
        [$owner, $customer, $booking] = $this->createConfirmedBooking('dispute-deadline-001');
        $booking->update([
            'booking_start' => now('UTC')->subHours(26),
            'booking_end' => now('UTC')->subHours(25),
        ]);

        $this->expectException(ValidationException::class);

        app(DisputeService::class)->open(
            $customer,
            $booking->fresh(),
            'other',
            'Laporan diajukan setelah batas waktu sengketa yang ditentukan oleh sistem.',
        );
    }

    private function setBookingEndedOneHourAgo(Booking $booking): void
    {
        $booking->update([
            'booking_start' => now('UTC')->subHours(2),
            'booking_end' => now('UTC')->subHour(),
            'completion_window_ends_at' => now('UTC')->addHours(23),
        ]);
    }

    private function createSuperAdmin(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    private function createConfirmedBooking(string $idempotencyKey): array
    {
        $owner = User::factory()->create([
            'role' => User::ROLE_OWNER,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $customer = User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $business = $owner->businesses()->create([
            'name' => 'Usaha Sengketa Uji',
            'slug' => 'usaha-sengketa-' . strtolower((string) Str::ulid()),
            'address' => 'Jalan Contoh Nomor 10',
            'timezone' => 'Asia/Jakarta',
            'status' => Business::STATUS_ACTIVE,
            'published_at' => now(),
        ]);
        $plan = SubscriptionPlan::query()->create([
            'slug' => 'plan-sengketa-' . strtolower((string) Str::ulid()),
            'name' => 'Plan Sengketa Uji',
            'monthly_price' => 100000,
            'duration_days' => 30,
            'max_businesses' => 2,
            'max_venues_per_business' => 5,
            'has_combined_reporting' => false,
            'is_active' => true,
        ]);
        $now = CarbonImmutable::now('UTC');
        Subscription::query()->create([
            'owner_id' => $owner->getKey(),
            'plan_id' => $plan->getKey(),
            'is_trial' => false,
            'status' => Subscription::STATUS_ACTIVE,
            'starts_at' => $now->subDay(),
            'ends_at' => $now->addDays(30),
            'grace_ends_at' => $now->addDays(37),
        ]);
        $category = Category::query()->firstOrFail();
        $venue = $business->venues()->create([
            'category_id' => $category->getKey(),
            'name' => 'Venue Sengketa Uji',
            'slug' => 'venue-sengketa-' . strtolower((string) Str::ulid()),
            'status' => Venue::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        for ($day = 0; $day <= 6; $day++) {
            VenueAvailabilityRule::query()->create([
                'venue_id' => $venue->getKey(),
                'day_of_week' => $day,
                'is_open' => true,
                'open_time' => '08:00:00',
                'close_time' => '20:00:00',
                'buffer_minutes' => 0,
            ]);
        }

        $venue->prices()->create([
            'pricing_unit' => 'hourly',
            'step_interval_minutes' => 60,
            'min_duration_units' => 1,
            'max_duration_units' => 8,
            'price_amount' => 100000,
            'is_active' => true,
        ]);
        $document = LegalDocument::query()->create([
            'type' => 'booking_terms',
            'version' => 'v1',
            'title' => 'Syarat Booking',
            'content' => 'Syarat booking untuk pengujian.',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);
        $acceptance = TermsAcceptance::query()->create([
            'user_id' => $customer->getKey(),
            'legal_document_id' => $document->getKey(),
            'context' => 'booking',
            'accepted_at' => now(),
        ]);
        $starts = CarbonImmutable::now('Asia/Jakarta')->addDays(10)->setTime(10, 0);
        $ends = $starts->addHour();
        $checkout = app(BookingEngineService::class)->createCheckout(
            $customer,
            $venue,
            $starts->utc(),
            $ends->utc(),
            $acceptance->getKey(),
            $idempotencyKey,
        );
        app(PaymentWebhookProcessor::class)->process('mock', [
            'event_id' => 'evt-' . $idempotencyKey,
            'gateway_transaction_id' => $checkout['payment']->gateway_transaction_id,
            'status' => Payment::STATUS_PAID,
            'paid_at' => CarbonImmutable::now('UTC')->toIso8601String(),
            'gateway_fee_amount' => 0,
            'amount' => $checkout['payment']->amount,
        ]);

        return [$owner, $customer, $checkout['booking']->fresh()];
    }
}
