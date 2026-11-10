<?php

declare(strict_types=1);

use App\Enums\CheckInStatus;
use App\Enums\PaymentStatus;
use App\Enums\SlotStatus;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Court;
use App\Models\CourtSlot;
use App\Models\Payment;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('services.midtrans.server_key', 'test-feature-server-key');
    config()->set('services.midtrans.api_url', 'https://api.sandbox.midtrans.com/v2');
});

function createTestCheckoutBooking(): array
{
    $venue = Venue::create([
        'name' => 'Senayan Gelora',
        'slug' => 'senayan-gelora',
        'city' => 'Jakarta Pusat',
        'address' => 'Jl. Gelora Bung Karno',
        'subscription_status' => 'active',
        'subscription_ends_at' => now()->addMonth(),
    ]);

    $court = Court::create([
        'venue_id' => $venue->id,
        'name' => 'Lap 1',
        'sport_type' => 'badminton',
        'default_hourly_rate' => 120000,
        'opening_time' => '07:00:00',
        'closing_time' => '22:00:00',
        'is_active' => true,
    ]);

    $player = User::factory()->create([
        'role' => 'player',
    ]);

    $booking = Booking::create([
        'booking_code' => Booking::generateBookingCode(),
        'user_id' => $player->id,
        'venue_id' => $venue->id,
        'total_amount' => 120000,
        'payment_status' => PaymentStatus::Pending,
        'check_in_status' => CheckInStatus::Unclaimed,
        'qr_token' => Booking::generateQrToken(),
        'qris_expires_at' => now()->addMinutes(10),
    ]);

    $slot = CourtSlot::create([
        'court_id' => $court->id,
        'date' => now()->format('Y-m-d'),
        'start_time' => '08:00:00',
        'end_time' => '09:00:00',
        'price' => 120000,
        'status' => SlotStatus::Held,
        'locked_until' => now()->addMinutes(10),
    ]);

    BookingItem::create([
        'booking_id' => $booking->id,
        'court_slot_id' => $slot->id,
        'price_locked' => 120000,
    ]);

    Cache::lock("court_slot:lock:{$slot->id}", 600, $booking->booking_code)->get();

    return [$booking->fresh(['items.courtSlot', 'user', 'venue']), $player, $slot];
}

test('player can initiate dynamic QRIS payment for held booking', function () {
    [$booking, $player, $slot] = createTestCheckoutBooking();

    Http::fake([
        'https://api.sandbox.midtrans.com/v2/charge' => Http::response([
            'status_code' => '201',
            'transaction_id' => 'midtrans-qris-001',
            'order_id' => $booking->booking_code,
            'gross_amount' => '120000.00',
            'payment_type' => 'qris',
            'transaction_status' => 'pending',
            'qr_string' => '000201010212...sample-qris-code...',
            'expiry_time' => now()->addMinutes(10)->format('Y-m-d H:i:s'),
            'actions' => [
                [
                    'name' => 'generate-qr-code',
                    'url' => 'https://api.sandbox.midtrans.com/v2/qris/midtrans-qris-001/qr-code',
                ],
            ],
        ], 201),
    ]);

    $response = $this->actingAs($player)
        ->postJson("/checkout/{$booking->booking_code}/pay");

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'data' => [
                'booking_code' => $booking->booking_code,
                'total_amount' => 120000,
                'payment_status' => 'pending',
                'midtrans_transaction_id' => 'midtrans-qris-001',
                'qr_string' => '000201010212...sample-qris-code...',
                'qr_url' => 'https://api.sandbox.midtrans.com/v2/qris/midtrans-qris-001/qr-code',
            ],
        ]);

    $this->assertDatabaseHas('payments', [
        'booking_id' => $booking->id,
        'order_id' => $booking->booking_code,
        'midtrans_transaction_id' => 'midtrans-qris-001',
        'status' => 'pending',
    ]);
});

test('re-initiating payment for booking with active QRIS reuses existing payment without duplicate charge', function () {
    [$booking, $player] = createTestCheckoutBooking();

    Http::fake([
        'https://api.sandbox.midtrans.com/v2/charge' => Http::response([
            'status_code' => '201',
            'transaction_id' => 'midtrans-qris-001',
            'order_id' => $booking->booking_code,
            'gross_amount' => '120000.00',
            'payment_type' => 'qris',
            'transaction_status' => 'pending',
            'qr_string' => '000201010212...sample-qris-code...',
            'expiry_time' => now()->addMinutes(10)->format('Y-m-d H:i:s'),
            'actions' => [
                [
                    'name' => 'generate-qr-code',
                    'url' => 'https://api.sandbox.midtrans.com/v2/qris/midtrans-qris-001/qr-code',
                ],
            ],
        ], 201),
    ]);

    // First call charges gateway
    $firstResponse = $this->actingAs($player)->postJson("/checkout/{$booking->booking_code}/pay");
    $firstResponse->assertStatus(200);

    // Second call should return existing active payment without calling charge API again
    $secondResponse = $this->actingAs($player)->postJson("/checkout/{$booking->booking_code}/pay");
    $secondResponse->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'data' => [
                'midtrans_transaction_id' => 'midtrans-qris-001',
            ],
        ]);

    Http::assertSentCount(1);
});

test('unauthorized user cannot initiate payment on another players booking', function () {
    [$booking, $player] = createTestCheckoutBooking();
    $otherPlayer = User::factory()->create(['role' => 'player']);

    $response = $this->actingAs($otherPlayer)
        ->postJson("/checkout/{$booking->booking_code}/pay");

    $response->assertStatus(403);
});

test('initiating payment for an expired booking hold returns 422', function () {
    [$booking, $player] = createTestCheckoutBooking();
    $booking->update([
        'qris_expires_at' => now()->subMinute(),
    ]);

    $response = $this->actingAs($player)
        ->postJson("/checkout/{$booking->booking_code}/pay");

    $response->assertStatus(422)
        ->assertJson([
            'status' => 'error',
            'message' => 'Checkout hold has expired.',
        ]);
});

test('webhook endpoint rejects invalid or tampered signature with 401', function () {
    [$booking] = createTestCheckoutBooking();

    $response = $this->postJson('/api/webhooks/midtrans', [
        'order_id' => $booking->booking_code,
        'status_code' => '200',
        'gross_amount' => '120000.00',
        'signature_key' => 'tampered_signature_hash',
        'transaction_status' => 'settlement',
    ]);

    $response->assertStatus(401);
});

test('webhook endpoint settles payment idempotently and commits court slots', function () {
    [$booking, $player, $slot] = createTestCheckoutBooking();

    $serverKey = 'test-feature-server-key';
    $signature = hash('sha512', $booking->booking_code.'200'.'120000.00'.$serverKey);

    $webhookPayload = [
        'order_id' => $booking->booking_code,
        'transaction_id' => 'midtrans-settle-001',
        'status_code' => '200',
        'gross_amount' => '120000.00',
        'payment_type' => 'qris',
        'transaction_status' => 'settlement',
        'signature_key' => $signature,
        'settlement_time' => '2026-11-10 10:05:00',
    ];

    // 1. Initial valid webhook delivery
    $response = $this->postJson('/api/webhooks/midtrans', $webhookPayload);
    $response->assertStatus(200)
        ->assertJson([
            'status' => 'success',
        ]);

    $booking->refresh();
    expect($booking->payment_status)->toBe(PaymentStatus::Paid)
        ->and($slot->fresh()->status)->toBe(SlotStatus::Booked);

    // 2. Duplicate webhook delivery
    $duplicateResponse = $this->postJson('/api/webhooks/midtrans', $webhookPayload);
    $duplicateResponse->assertStatus(200)
        ->assertJson([
            'status' => 'success',
        ]);

    expect($booking->fresh()->payment_status)->toBe(PaymentStatus::Paid)
        ->and(Payment::where('booking_id', $booking->id)->count())->toBe(1);
});

test('checkout status polling endpoint returns payment status and ticket token', function () {
    [$booking, $player] = createTestCheckoutBooking();

    // 1. When pending
    $responsePending = $this->actingAs($player)
        ->getJson("/checkout/{$booking->booking_code}/status");

    $responsePending->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'data' => [
                'booking_code' => $booking->booking_code,
                'payment_status' => 'pending',
                'qr_token' => null,
            ],
        ]);

    // 2. When paid
    $booking->update([
        'payment_status' => PaymentStatus::Paid,
        'paid_at' => now(),
    ]);

    $responsePaid = $this->actingAs($player)
        ->getJson("/checkout/{$booking->booking_code}/status");

    $responsePaid->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'data' => [
                'booking_code' => $booking->booking_code,
                'payment_status' => 'paid',
                'qr_token' => $booking->qr_token,
            ],
        ]);
});

test('manual status inquiry queries midtrans status API and reconciles booking', function () {
    [$booking, $player, $slot] = createTestCheckoutBooking();

    $serverKey = 'test-feature-server-key';
    $signature = hash('sha512', $booking->booking_code.'200'.'120000.00'.$serverKey);

    Http::fake([
        "https://api.sandbox.midtrans.com/v2/{$booking->booking_code}/status" => Http::response([
            'status_code' => '200',
            'transaction_id' => 'midtrans-inquire-001',
            'order_id' => $booking->booking_code,
            'gross_amount' => '120000.00',
            'transaction_status' => 'settlement',
            'signature_key' => $signature,
            'settlement_time' => '2026-11-10 10:08:00',
        ], 200),
    ]);

    $response = $this->actingAs($player)
        ->postJson("/checkout/{$booking->booking_code}/inquire");

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'data' => [
                'booking_code' => $booking->booking_code,
                'payment_status' => 'paid',
            ],
        ]);

    expect($booking->fresh()->payment_status)->toBe(PaymentStatus::Paid)
        ->and($slot->fresh()->status)->toBe(SlotStatus::Booked);
});

test('manual status inquiry handles gateway communication failure gracefully with 502', function () {
    [$booking, $player] = createTestCheckoutBooking();

    Http::fake([
        "https://api.sandbox.midtrans.com/v2/{$booking->booking_code}/status" => Http::response([
            'status_message' => 'Internal server error from Midtrans',
        ], 500),
    ]);

    $response = $this->actingAs($player)
        ->postJson("/checkout/{$booking->booking_code}/inquire");

    $response->assertStatus(502)
        ->assertJson([
            'status' => 'error',
        ]);
});
