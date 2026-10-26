<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Business;
use App\Models\Category;
use App\Models\Dispute;
use App\Models\LegalDocument;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\TermsAcceptance;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueAvailabilityRule;
use App\Services\Booking\BookingCompletionService;
use App\Services\Booking\BookingEngineService;
use App\Services\Disputes\DisputeService;
use App\Services\Payments\PaymentWebhookProcessor;
use Carbon\CarbonImmutable;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class DisputeServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CategorySeeder::class);
    }

    public function test_customer_can_open_dispute_and_owner_can_respond(): void
    {
        [$owner, $customer, $booking] = $this->createAwaitingCompletionBooking('dispute-open-001');

        $dispute = app(DisputeService::class)->open(
            $customer,
            $booking,
            Dispute::REASON_SERVICE_NOT_PROVIDED,
            'Venue tidak menyediakan fasilitas yang tercantum.',
        );

        $this->assertSame(Dispute::STATUS_AWAITING_RESPONSE, $dispute->status);
        $this->assertSame($owner->getKey(), $dispute->against_user_id);
        $this->assertTrue($dispute->response_deadline->isFuture());
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->getKey(),
            'status' => Booking::STATUS_DISPUTED,
        ]);

        $message = app(DisputeService::class)->respond($owner, $dispute, 'Kami akan meninjau laporan ini.');

        $this->assertSame($owner->getKey(), $message->sender_user_id);
        $this->assertDatabaseHas('disputes', [
            'id' => $dispute->getKey(),
            'status' => Dispute::STATUS_UNDER_REVIEW,
        ]);
    }

    public function test_dispute_evidence_is_stored_on_private_disk(): void
    {
        Storage::fake('local');
        [$owner, $customer, $booking] = $this->createAwaitingCompletionBooking('dispute-evidence-001');
        $dispute = app(DisputeService::class)->open(
            $customer,
            $booking,
            Dispute::REASON_VENUE_CONDITION,
            'Kondisi venue berbeda dari informasi yang ditampilkan.',
        );

        $evidence = app(DisputeService::class)->addEvidence(
            $customer,
            $dispute,
            UploadedFile::fake()->image('venue-proof.png')->size(100),
            'Foto kondisi venue',
        );

        $this->assertTrue(Storage::disk('local')->exists($evidence->file_path));
        $this->assertSame('image/png', $evidence->mime_type);
        $this->assertDatabaseHas('dispute_evidence', [
            'id' => $evidence->getKey(),
            'dispute_id' => $dispute->getKey(),
            'uploaded_by' => $customer->getKey(),
        ]);
    }

    private function createAwaitingCompletionBooking(string $idempotencyKey): array
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
            'name' => 'Usaha Dispute Uji',
            'slug' => 'usaha-dispute-' . strtolower((string) Str::ulid()),
            'address' => 'Jalan Contoh Nomor 10',
            'timezone' => 'Asia/Jakarta',
            'status' => Business::STATUS_ACTIVE,
            'published_at' => now(),
        ]);
        $plan = SubscriptionPlan::query()->create([
            'slug' => 'plan-dispute-' . strtolower((string) Str::ulid()),
            'name' => 'Plan Dispute Uji',
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
            'name' => 'Venue Dispute Uji',
            'slug' => 'venue-dispute-' . strtolower((string) Str::ulid()),
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
        $checkout = app(BookingEngineService::class)->createCheckout(
            $customer,
            $venue,
            $starts->utc(),
            $starts->addHour()->utc(),
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

        $booking = $checkout['booking']->fresh();
        $booking->update([
            'booking_start' => now('UTC')->subHours(2),
            'booking_end' => now('UTC')->subHour(),
        ]);
        $completion = app(BookingCompletionService::class);
        $completion->startByOwner($owner, $booking->fresh());
        $completion->advanceEndedBookings();

        return [$owner, $customer, $booking->fresh()];
    }
}
