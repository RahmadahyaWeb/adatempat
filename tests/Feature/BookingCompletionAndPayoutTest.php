<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Business;
use App\Models\Category;
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
use App\Services\Booking\BookingCompletionService;
use App\Services\Booking\BookingEngineService;
use App\Services\Finance\PayoutService;
use App\Services\Payments\PaymentWebhookProcessor;
use Carbon\CarbonImmutable;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class BookingCompletionAndPayoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CategorySeeder::class);
    }

    public function test_customer_confirmation_makes_payout_eligible_and_super_admin_can_mark_transfer_paid(): void
    {
        [$owner, $customer, $booking] = $this->createConfirmedBooking('payout-complete-001');
        $booking->update([
            'booking_start' => now('UTC')->subHours(2),
            'booking_end' => now('UTC')->subHour(),
        ]);

        $completion = app(BookingCompletionService::class);
        $completion->startByOwner($owner, $booking->fresh());
        $completion->requestCompletionByOwner($owner, $booking->fresh());
        $completed = $completion->confirmCompletionByCustomer($customer, $booking->fresh());

        $this->assertSame(Booking::STATUS_COMPLETED, $completed->status);

        $admin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $account = PayoutAccount::query()->create([
            'owner_id' => $owner->getKey(),
            'bank_code' => 'BCA',
            'bank_name' => 'Bank Central Asia',
            'account_number_encrypted' => '123456789012',
            'account_number_last4' => '9012',
            'account_holder_name' => 'Pemilik Usaha',
            'verification_status' => PayoutAccount::STATUS_VERIFIED,
            'verified_by' => $admin->getKey(),
            'verified_at' => now(),
        ]);

        $prepared = app(PayoutService::class)->prepareEligiblePayouts();

        $this->assertSame(1, $prepared['created']);
        $this->assertSame(1, $prepared['eligible']);
        $payout = Payout::query()->where('booking_id', $booking->getKey())->firstOrFail();
        $this->assertSame($account->getKey(), $payout->payout_account_id);
        $this->assertSame(100000, $payout->net_amount);

        $paid = app(PayoutService::class)->markPaid($payout, $admin, 'BANK-TRANSFER-REF-001');

        $this->assertSame(Payout::STATUS_PAID, $paid->status);
        $this->assertSame('BANK-TRANSFER-REF-001', $paid->transfer_reference);
        $this->assertDatabaseCount('ledger_entries', 4);
    }

    public function test_auto_completion_runs_after_the_twenty_four_hour_window_and_payout_waits_for_verified_account(): void
    {
        [$owner, $customer, $booking] = $this->createConfirmedBooking('payout-auto-complete-001');
        $booking->update([
            'booking_start' => now('UTC')->subHours(2),
            'booking_end' => now('UTC')->subHour(),
        ]);

        $completion = app(BookingCompletionService::class);
        $completion->startByOwner($owner, $booking->fresh());
        $completion->requestCompletionByOwner($owner, $booking->fresh());
        $deadline = now('UTC')->subMinute();
        $booking->update(['completion_window_ends_at' => $deadline]);

        $this->assertSame(1, $completion->autoCompleteExpired());

        $completed = $booking->fresh();
        $this->assertSame(Booking::STATUS_COMPLETED, $completed->status);
        $this->assertEquals($deadline->timestamp, $completed->completed_at->timestamp);

        $prepared = app(PayoutService::class)->prepareEligiblePayouts();

        $this->assertSame(1, $prepared['created']);
        $this->assertSame(1, $prepared['held']);
        $this->assertDatabaseHas('payouts', [
            'booking_id' => $booking->getKey(),
            'status' => Payout::STATUS_HELD,
            'payout_account_id' => null,
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
            'name' => 'Usaha Payout Uji',
            'slug' => 'usaha-payout-' . strtolower((string) Str::ulid()),
            'address' => 'Jalan Contoh Nomor 10',
            'timezone' => 'Asia/Jakarta',
            'status' => Business::STATUS_ACTIVE,
            'published_at' => now(),
        ]);
        $plan = SubscriptionPlan::query()->create([
            'slug' => 'plan-payout-' . strtolower((string) Str::ulid()),
            'name' => 'Plan Payout Uji',
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
            'name' => 'Venue Payout Uji',
            'slug' => 'venue-payout-' . strtolower((string) Str::ulid()),
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
