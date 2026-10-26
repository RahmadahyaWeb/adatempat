<?php

use App\Http\Controllers\PaymentWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/payments/{provider}', [PaymentWebhookController::class, 'handle'])
    ->where('provider', '[a-z0-9_-]+')
    ->middleware('throttle:120,1')
    ->name('webhooks.payments.handle');
