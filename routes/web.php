<?php

use App\Http\Controllers\Admin\AdminVenueController;
use App\Http\Controllers\Admin\AdminVenueSubscriptionController;
use App\Http\Controllers\Auth\MagicLinkController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\HealthcheckController;
use App\Http\Controllers\Operator\OperatorCourtController;
use App\Http\Controllers\Operator\OperatorCourtPricingRuleController;
use App\Http\Controllers\Operator\OperatorCourtSlotController;
use App\Http\Controllers\Operator\OperatorVenueController;
use App\Http\Middleware\EnsureUserIsSuperadmin;
use App\Http\Middleware\EnsureUserIsVenueOperator;
use App\Http\Middleware\EnsureVenueSubscriptionActive;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/health', HealthcheckController::class)->name('health');

Route::prefix('auth')->name('auth.')->group(function () {
    Route::post('/magic-link', [MagicLinkController::class, 'send'])->name('magic-link.send');
    Route::get('/magic-link/verify/{user}', [MagicLinkController::class, 'verify'])->name('magic-link.verify');
});

Route::prefix('checkout')->name('checkout.')->middleware('auth')->group(function () {
    Route::post('/hold', [CheckoutController::class, 'hold'])->name('hold')->middleware('throttle:10,1');
    Route::post('/{booking:booking_code}/abandon', [CheckoutController::class, 'abandon'])->name('abandon');
});

Route::prefix('operator')
    ->name('operator.')
    ->middleware(['auth', EnsureUserIsVenueOperator::class, EnsureVenueSubscriptionActive::class])
    ->group(function () {
        Route::get('/venue', [OperatorVenueController::class, 'show'])->name('venue.show');
        Route::put('/venue', [OperatorVenueController::class, 'update'])->name('venue.update');

        Route::get('/courts', [OperatorCourtController::class, 'index'])->name('courts.index');
        Route::post('/courts', [OperatorCourtController::class, 'store'])->name('courts.store');
        Route::get('/courts/{court}', [OperatorCourtController::class, 'show'])->name('courts.show');
        Route::put('/courts/{court}', [OperatorCourtController::class, 'update'])->name('courts.update');
        Route::delete('/courts/{court}', [OperatorCourtController::class, 'destroy'])->name('courts.destroy');

        Route::get('/courts/{court}/slots', [OperatorCourtSlotController::class, 'index'])->name('courts.slots.index');

        Route::get('/courts/{court}/pricing-rules', [OperatorCourtPricingRuleController::class, 'index'])->name('courts.pricing-rules.index');
        Route::post('/courts/{court}/pricing-rules', [OperatorCourtPricingRuleController::class, 'store'])->name('courts.pricing-rules.store');
        Route::put('/courts/{court}/pricing-rules/{pricingRule}', [OperatorCourtPricingRuleController::class, 'update'])->name('courts.pricing-rules.update');
        Route::delete('/courts/{court}/pricing-rules/{pricingRule}', [OperatorCourtPricingRuleController::class, 'destroy'])->name('courts.pricing-rules.destroy');
    });

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', EnsureUserIsSuperadmin::class])
    ->group(function () {
        Route::post('/venues', [AdminVenueController::class, 'store'])->name('venues.store');
        Route::patch('/venues/{venue}/subscription', [AdminVenueSubscriptionController::class, 'update'])->name('venues.subscription.update');
    });
