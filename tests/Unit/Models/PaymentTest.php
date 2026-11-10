<?php

declare(strict_types=1);

use App\Models\Booking;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

test('payment model casts attributes correctly', function () {
    $payment = new Payment([
        'booking_id' => 1,
        'order_id' => 'ADT-20261110-1234',
        'midtrans_transaction_id' => 'mid-txn-123456',
        'gross_amount' => 150000,
        'payment_type' => 'qris',
        'status' => 'settlement',
        'qr_string' => '000201010212266...dummy-qr...',
        'qr_url' => 'https://api.midtrans.com/v2/qris/123/qr-code',
        'expiry_time' => '2026-11-10 10:10:00',
        'settled_at' => '2026-11-10 10:05:00',
        'raw_payload' => ['transaction_status' => 'settlement'],
    ]);

    expect($payment->gross_amount)->toBe(150000)
        ->and($payment->order_id)->toBe('ADT-20261110-1234')
        ->and($payment->midtrans_transaction_id)->toBe('mid-txn-123456')
        ->and($payment->payment_type)->toBe('qris')
        ->and($payment->status)->toBe('settlement')
        ->and($payment->qr_string)->toBe('000201010212266...dummy-qr...')
        ->and($payment->expiry_time)->toBeInstanceOf(Carbon::class)
        ->and($payment->settled_at)->toBeInstanceOf(Carbon::class)
        ->and($payment->raw_payload)->toBeArray()
        ->and($payment->raw_payload['transaction_status'])->toBe('settlement');
});

test('payment relationships return correct relations', function () {
    $payment = new Payment;

    expect($payment->booking())->toBeInstanceOf(BelongsTo::class)
        ->and($payment->booking()->getRelated())->toBeInstanceOf(Booking::class);
});
