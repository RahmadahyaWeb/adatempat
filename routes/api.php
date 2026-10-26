<?php

use App\Http\Controllers\PaymentWebhookController;
use App\Http\Controllers\RefundWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/payments/{provider}', [PaymentWebhookController::class, 'handle'])
    ->where('provider', '[a-z0-9_-]+')
    ->middleware('throttle:120,1')
    ->name('webhooks.payments.handle');

Route::post('/webhooks/refunds/{provider}', [RefundWebhookController::class, 'handle'])
    ->where('provider', '[a-z0-9_-]+')
    ->middleware('throttle:120,1')
    ->name('webhooks.refunds.handle');
