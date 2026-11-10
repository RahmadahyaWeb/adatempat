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
use App\Services\BookingConcurrencyService;
use App\Services\MidtransGatewayService;
use App\Services\PaymentReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('services.midtrans.server_key', 'test-server-key-reconciliation');
    config()->set('services.midtrans.api_url', 'https://api.sandbox.midtrans.com/v2');
});

function createHeldBookingWithSlots(int $slotCount = 2, int $totalAmount = 200000): array
{
    $venue = Venue::create([
        'name' => 'Senayan Arena',
        'slug' => 'senayan-arena',
        'city' => 'Jakarta',
        'address' => 'Jl. Asia Afrika',
        'subscription_status' => 'active',
        'subscription_ends_at' => now()->addMonth(),
    ]);

    $court = Court::create([
        'venue_id' => $venue->id,
        'name' => 'Court 1',
        'sport_type' => 'badminton',
        'default_hourly_rate' => 100000,
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
        'total_amount' => $totalAmount,
        'payment_status' => PaymentStatus::Pending,
        'check_in_status' => CheckInStatus::Unclaimed,
        'qr_token' => Booking::generateQrToken(),
        'qris_expires_at' => now()->addMinutes(10),
    ]);

    $slots = [];
    for ($i = 0; $i < $slotCount; $i++) {
        $slot = CourtSlot::create([
            'court_id' => $court->id,
            'date' => now()->format('Y-m-d'),
            'start_time' => sprintf('%02d:00:00', 8 + $i),
            'end_time' => sprintf('%02d:00:00', 9 + $i),
            'price' => (int) ($totalAmount / $slotCount),
            'status' => SlotStatus::Held,
            'locked_until' => now()->addMinutes(10),
        ]);

        BookingItem::create([
            'booking_id' => $booking->id,
            'court_slot_id' => $slot->id,
            'price_locked' => $slot->price,
        ]);

        // Place Redis lock
        Cache::lock("court_slot:lock:{$slot->id}", 600, $booking->booking_code)->get();

        $slots[] = $slot;
    }

    return [$booking->fresh(['items.courtSlot', 'venue', 'user']), $slots];
}

test('initiateQrisPayment calls gateway, persists payment record, and updates booking metadata', function () {
    [$booking] = createHeldBookingWithSlots();

    $gatewayMock = Mockery::mock(MidtransGatewayService::class);
    $gatewayMock->shouldReceive('chargeDynamicQris')
        ->once()
        ->with(Mockery::on(fn (Booking $b) => $b->id === $booking->id))
        ->andReturn([
            'transaction_id' => 'mid-txn-999',
            'order_id' => $booking->booking_code,
            'gross_amount' => 200000,
            'qr_string' => '000201010212...sample...qris',
            'qr_url' => 'https://api.midtrans.com/v2/qris/mid-txn-999/qr-code',
            'expiry_time' => now()->addMinutes(10)->format('Y-m-d H:i:s'),
            'transaction_status' => 'pending',
            'raw' => ['status_code' => '201'],
        ]);

    $service = new PaymentReconciliationService($gatewayMock, new BookingConcurrencyService);
    $payment = $service->initiateQrisPayment($booking);

    expect($payment)->toBeInstanceOf(Payment::class)
        ->and($payment->booking_id)->toBe($booking->id)
        ->and($payment->order_id)->toBe($booking->booking_code)
        ->and($payment->midtrans_transaction_id)->toBe('mid-txn-999')
        ->and($payment->qr_string)->toBe('000201010212...sample...qris')
        ->and($payment->qr_url)->toBe('https://api.midtrans.com/v2/qris/mid-txn-999/qr-code')
        ->and($payment->gross_amount)->toBe(200000);

    $refreshedBooking = $booking->fresh();
    expect($refreshedBooking->midtrans_transaction_id)->toBe('mid-txn-999')
        ->and($refreshedBooking->qris_url)->toBe('https://api.midtrans.com/v2/qris/mid-txn-999/qr-code');
});

test('settlement webhook transitions booking to paid, commits slots to booked, and releases redis locks', function () {
    [$booking, $slots] = createHeldBookingWithSlots();

    $gatewayMock = Mockery::mock(MidtransGatewayService::class);
    $gatewayMock->shouldReceive('verifyWebhookSignature')->andReturn(true);

    $payload = [
        'order_id' => $booking->booking_code,
        'transaction_id' => 'mid-txn-12345',
        'status_code' => '200',
        'gross_amount' => '200000.00',
        'transaction_status' => 'settlement',
        'signature_key' => 'valid-signature',
        'settlement_time' => '2026-11-10 10:05:00',
    ];

    $service = new PaymentReconciliationService($gatewayMock, new BookingConcurrencyService);
    $result = $service->processWebhook($payload);

    expect($result['status'])->toBe('success')
        ->and($result['booking_status'])->toBe(PaymentStatus::Paid->value);

    // Verify booking state
    $booking->refresh();
    expect($booking->payment_status)->toBe(PaymentStatus::Paid)
        ->and($booking->paid_at)->not->toBeNull()
        ->and($booking->midtrans_transaction_id)->toBe('mid-txn-12345');

    // Verify court slots committed to booked
    foreach ($slots as $slot) {
        $slot->refresh();
        expect($slot->status)->toBe(SlotStatus::Booked)
            ->and($slot->locked_until)->toBeNull();

        // Verify Redis lock released and can be acquired by another process
        $acquired = Cache::lock("court_slot:lock:{$slot->id}", 10)->get();
        expect($acquired)->toBeTrue();
        Cache::lock("court_slot:lock:{$slot->id}")->forceRelease();
    }

    // Verify payment audit log
    $payment = Payment::where('booking_id', $booking->id)->first();
    expect($payment)->not->toBeNull()
        ->and($payment->status)->toBe('settlement')
        ->and($payment->settled_at)->not->toBeNull();
});

test('settlement webhook rejects invalid status transition when booking is expired', function () {
    [$booking, $slots] = createHeldBookingWithSlots();
    $booking->update(['payment_status' => PaymentStatus::Expired]);

    $gatewayMock = Mockery::mock(MidtransGatewayService::class);
    $gatewayMock->shouldReceive('verifyWebhookSignature')->andReturn(true);

    $payload = [
        'order_id' => $booking->booking_code,
        'transaction_id' => 'mid-txn-late',
        'status_code' => '200',
        'gross_amount' => '200000.00',
        'transaction_status' => 'settlement',
        'signature_key' => 'valid-signature',
        'settlement_time' => '2026-11-10 10:15:00',
    ];

    $service = new PaymentReconciliationService($gatewayMock, new BookingConcurrencyService);

    expect(fn () => $service->processWebhook($payload))
        ->toThrow(HttpException::class, 'Cannot settle booking in [expired] status.');
});

test('duplicate webhook notifications for an already paid booking return HTTP 200 idempotently', function () {
    [$booking, $slots] = createHeldBookingWithSlots();

    $gatewayMock = Mockery::mock(MidtransGatewayService::class);
    $gatewayMock->shouldReceive('verifyWebhookSignature')->andReturn(true);

    $payload = [
        'order_id' => $booking->booking_code,
        'transaction_id' => 'mid-txn-12345',
        'status_code' => '200',
        'gross_amount' => '200000.00',
        'transaction_status' => 'settlement',
        'signature_key' => 'valid-signature',
        'settlement_time' => '2026-11-10 10:05:00',
    ];

    $service = new PaymentReconciliationService($gatewayMock, new BookingConcurrencyService);

    // First delivery
    $firstRun = $service->processWebhook($payload);
    expect($firstRun['status'])->toBe('success');

    $booking->refresh();
    $firstPaidAt = $booking->paid_at;
    $initialPaymentsCount = Payment::where('booking_id', $booking->id)->count();

    // Second delivery (duplicate)
    $secondRun = $service->processWebhook($payload);
    expect($secondRun['status'])->toBe('success')
        ->and($secondRun['idempotent'])->toBeTrue();

    // Third delivery (triplicate)
    $thirdRun = $service->processWebhook($payload);
    expect($thirdRun['status'])->toBe('success')
        ->and($thirdRun['idempotent'])->toBeTrue();

    // Verify booking state and timestamps remain unchanged
    $booking->refresh();
    expect($booking->payment_status)->toBe(PaymentStatus::Paid)
        ->and($booking->paid_at->toISOString())->toBe($firstPaidAt->toISOString());

    // Payments count should not multiply unbounded
    expect(Payment::where('booking_id', $booking->id)->count())->toBe($initialPaymentsCount);
});

test('expire or cancel webhook transitions booking to expired and resets slots to available', function () {
    [$booking, $slots] = createHeldBookingWithSlots();

    $gatewayMock = Mockery::mock(MidtransGatewayService::class);
    $gatewayMock->shouldReceive('verifyWebhookSignature')->andReturn(true);

    $payload = [
        'order_id' => $booking->booking_code,
        'transaction_id' => 'mid-txn-expired',
        'status_code' => '202',
        'gross_amount' => '200000.00',
        'transaction_status' => 'expire',
        'signature_key' => 'valid-signature',
    ];

    $service = new PaymentReconciliationService($gatewayMock, new BookingConcurrencyService);
    $result = $service->processWebhook($payload);

    expect($result['status'])->toBe('success')
        ->and($result['booking_status'])->toBe(PaymentStatus::Expired->value);

    $booking->refresh();
    expect($booking->payment_status)->toBe(PaymentStatus::Expired);

    // Verify court slots reset to available
    foreach ($slots as $slot) {
        $slot->refresh();
        expect($slot->status)->toBe(SlotStatus::Available)
            ->and($slot->locked_until)->toBeNull();

        // Verify Redis lock released
        $acquired = Cache::lock("court_slot:lock:{$slot->id}", 10)->get();
        expect($acquired)->toBeTrue();
        Cache::lock("court_slot:lock:{$slot->id}")->forceRelease();
    }
});

test('processWebhook throws 401 when signature verification fails', function () {
    [$booking] = createHeldBookingWithSlots();

    $gatewayMock = Mockery::mock(MidtransGatewayService::class);
    $gatewayMock->shouldReceive('verifyWebhookSignature')->andReturn(false);

    $service = new PaymentReconciliationService($gatewayMock, new BookingConcurrencyService);

    expect(fn () => $service->processWebhook([
        'order_id' => $booking->booking_code,
        'status_code' => '200',
        'gross_amount' => '200000.00',
        'signature_key' => 'invalid-hash',
        'transaction_status' => 'settlement',
    ]))->toThrow(HttpException::class, 'Invalid Midtrans signature.');
});

test('inquireAndReconcile fetches gateway status and reconciles pending booking', function () {
    [$booking, $slots] = createHeldBookingWithSlots();

    $gatewayMock = Mockery::mock(MidtransGatewayService::class);
    $gatewayMock->shouldReceive('fetchTransactionStatus')
        ->once()
        ->with($booking->booking_code)
        ->andReturn([
            'order_id' => $booking->booking_code,
            'transaction_id' => 'mid-txn-inquired',
            'status_code' => '200',
            'gross_amount' => '200000.00',
            'transaction_status' => 'settlement',
            'signature_key' => 'valid-signature',
            'settlement_time' => now()->format('Y-m-d H:i:s'),
        ]);

    $gatewayMock->shouldReceive('verifyWebhookSignature')->andReturn(true);

    $service = new PaymentReconciliationService($gatewayMock, new BookingConcurrencyService);
    $reconciledBooking = $service->inquireAndReconcile($booking);

    expect($reconciledBooking->payment_status)->toBe(PaymentStatus::Paid);

    foreach ($slots as $slot) {
        expect($slot->fresh()->status)->toBe(SlotStatus::Booked);
    }
});
