<?php

declare(strict_types=1);

use App\Models\Booking;
use App\Services\MidtransGatewayService;
use Carbon\Carbon;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.midtrans.server_key', 'test-server-key-12345');
    config()->set('services.midtrans.api_url', 'https://api.sandbox.midtrans.com/v2');
});

test('chargeDynamicQris formats dynamic QRIS payload with 10-minute expiry and sends basic auth', function () {
    Carbon::setTestNow('2026-11-10 10:00:00');

    Http::fake([
        'https://api.sandbox.midtrans.com/v2/charge' => Http::response([
            'status_code' => '201',
            'status_message' => 'Success, QRIS transaction is created',
            'transaction_id' => 'midtrans-uuid-001',
            'order_id' => 'ADT-20261110-TEST',
            'gross_amount' => '150000.00',
            'payment_type' => 'qris',
            'transaction_time' => '2026-11-10 10:00:00',
            'transaction_status' => 'pending',
            'qr_string' => '000201010212265...midtrans...qris',
            'expiry_time' => '2026-11-10 10:10:00',
            'actions' => [
                [
                    'name' => 'generate-qr-code',
                    'method' => 'GET',
                    'url' => 'https://api.sandbox.midtrans.com/v2/qris/midtrans-uuid-001/qr-code',
                ],
            ],
        ], 201),
    ]);

    $booking = new Booking([
        'booking_code' => 'ADT-20261110-TEST',
        'total_amount' => 150000,
        'created_at' => Carbon::now(),
    ]);

    $service = new MidtransGatewayService;
    $response = $service->chargeDynamicQris($booking);

    expect($response['transaction_id'])->toBe('midtrans-uuid-001')
        ->and($response['order_id'])->toBe('ADT-20261110-TEST')
        ->and($response['qr_string'])->toBe('000201010212265...midtrans...qris')
        ->and($response['qr_url'])->toBe('https://api.sandbox.midtrans.com/v2/qris/midtrans-uuid-001/qr-code')
        ->and($response['gross_amount'])->toBe(150000);

    Http::assertSent(function (Request $request) {
        $data = $request->data();

        return $request->url() === 'https://api.sandbox.midtrans.com/v2/charge'
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('test-server-key-12345:'))
            && $data['payment_type'] === 'qris'
            && $data['transaction_details']['order_id'] === 'ADT-20261110-TEST'
            && $data['transaction_details']['gross_amount'] === 150000
            && $data['custom_expiry']['expiry_duration'] === 10
            && $data['custom_expiry']['unit'] === 'minute';
    });
});

test('verifyWebhookSignature validates genuine SHA-512 signature using timing-safe comparison', function () {
    $service = new MidtransGatewayService;

    $orderId = 'ADT-20261110-TEST';
    $statusCode = '200';
    $grossAmount = '150000.00';
    $serverKey = 'test-server-key-12345';

    $validSignature = hash('sha512', $orderId.$statusCode.$grossAmount.$serverKey);

    $validPayload = [
        'order_id' => $orderId,
        'status_code' => $statusCode,
        'gross_amount' => $grossAmount,
        'signature_key' => $validSignature,
        'transaction_status' => 'settlement',
    ];

    expect($service->verifyWebhookSignature($validPayload))->toBeTrue();

    // Tampered signature or amount
    $tamperedPayload = $validPayload;
    $tamperedPayload['signature_key'] = 'tampered-signature-hash';
    expect($service->verifyWebhookSignature($tamperedPayload))->toBeFalse();

    $tamperedAmountPayload = $validPayload;
    $tamperedAmountPayload['gross_amount'] = '50000.00';
    expect($service->verifyWebhookSignature($tamperedAmountPayload))->toBeFalse();

    // Missing signature
    unset($validPayload['signature_key']);
    expect($service->verifyWebhookSignature($validPayload))->toBeFalse();
});

test('fetchTransactionStatus queries midtrans status API with order id', function () {
    Http::fake([
        'https://api.sandbox.midtrans.com/v2/ADT-20261110-TEST/status' => Http::response([
            'status_code' => '200',
            'transaction_id' => 'midtrans-uuid-001',
            'order_id' => 'ADT-20261110-TEST',
            'transaction_status' => 'settlement',
            'fraud_status' => 'accept',
            'gross_amount' => '150000.00',
            'signature_key' => hash('sha512', 'ADT-20261110-TEST200150000.00test-server-key-12345'),
        ], 200),
    ]);

    $service = new MidtransGatewayService;
    $status = $service->fetchTransactionStatus('ADT-20261110-TEST');

    expect($status['transaction_status'])->toBe('settlement')
        ->and($status['order_id'])->toBe('ADT-20261110-TEST');

    Http::assertSent(function (Request $request) {
        return $request->url() === 'https://api.sandbox.midtrans.com/v2/ADT-20261110-TEST/status'
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('test-server-key-12345:'));
    });
});
