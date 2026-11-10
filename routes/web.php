<?php

use App\Http\Controllers\Admin\AdminCityController;
use App\Http\Controllers\Admin\AdminSportTypeController;
use App\Http\Controllers\Admin\AdminVenueController;
use App\Http\Controllers\Admin\AdminVenueOperatorController;
use App\Http\Controllers\Admin\AdminVenueSubscriptionController;
use App\Http\Controllers\Auth\MagicLinkController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\HealthcheckController;
use App\Http\Controllers\Operator\OperatorCheckInController;
use App\Http\Controllers\Operator\OperatorCourtController;
use App\Http\Controllers\Operator\OperatorCourtPricingRuleController;
use App\Http\Controllers\Operator\OperatorCourtSlotController;
use App\Http\Controllers\Operator\OperatorSlotBlockingController;
use App\Http\Controllers\Operator\OperatorTimelineController;
use App\Http\Controllers\Operator\OperatorVenueController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\Player\PlayerRescheduleController;
use App\Http\Controllers\Player\PlayerTicketController;
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
    Route::post('/{booking:booking_code}/pay', [PaymentController::class, 'pay'])->name('pay');
    Route::get('/{booking:booking_code}/status', [PaymentController::class, 'status'])->name('status');
    Route::post('/{booking:booking_code}/inquire', [PaymentController::class, 'inquire'])->name('inquire');
});

Route::prefix('player')
    ->name('player.')
    ->middleware('auth')
    ->group(function () {
        Route::get('/bookings', [PlayerTicketController::class, 'index'])->name('bookings.index');
        Route::get('/tickets/{booking:booking_code}', [PlayerTicketController::class, 'show'])->name('tickets.show');
        Route::post('/bookings/{booking:booking_code}/reschedule', PlayerRescheduleController::class)->name('bookings.reschedule');
    });

Route::prefix('profile')
    ->name('profile.')
    ->middleware('auth')
    ->group(function () {
        Route::get('/bookings', [PlayerTicketController::class, 'index'])->name('bookings.index');
        Route::get('/tickets/{booking:booking_code}', [PlayerTicketController::class, 'show'])->name('tickets.show');
        Route::post('/bookings/{booking:booking_code}/reschedule', PlayerRescheduleController::class)->name('bookings.reschedule');
    });

Route::prefix('operator')
    ->name('operator.')
    ->middleware(['auth', EnsureUserIsVenueOperator::class, EnsureVenueSubscriptionActive::class])
    ->group(function () {
        Route::get('/venue', [OperatorVenueController::class, 'show'])->name('venue.show');
        Route::put('/venue', [OperatorVenueController::class, 'update'])->name('venue.update');

        Route::get('/timeline', OperatorTimelineController::class)->name('timeline');

        Route::post('/check-in', OperatorCheckInController::class)->name('check-in');

        Route::get('/courts', [OperatorCourtController::class, 'index'])->name('courts.index');
        Route::post('/courts', [OperatorCourtController::class, 'store'])->name('courts.store');
        Route::get('/courts/{court}', [OperatorCourtController::class, 'show'])->name('courts.show');
        Route::put('/courts/{court}', [OperatorCourtController::class, 'update'])->name('courts.update');
        Route::delete('/courts/{court}', [OperatorCourtController::class, 'destroy'])->name('courts.destroy');

        Route::get('/courts/{court}/slots', [OperatorCourtSlotController::class, 'index'])->name('courts.slots.index');
        Route::post('/slots/{slot}/block', [OperatorSlotBlockingController::class, 'block'])->name('slots.block');
        Route::post('/slots/{slot}/unblock', [OperatorSlotBlockingController::class, 'unblock'])->name('slots.unblock');

        Route::get('/courts/{court}/pricing-rules', [OperatorCourtPricingRuleController::class, 'index'])->name('courts.pricing-rules.index');
        Route::post('/courts/{court}/pricing-rules', [OperatorCourtPricingRuleController::class, 'store'])->name('courts.pricing-rules.store');
        Route::put('/courts/{court}/pricing-rules/{pricingRule}', [OperatorCourtPricingRuleController::class, 'update'])->name('courts.pricing-rules.update');
        Route::delete('/courts/{court}/pricing-rules/{pricingRule}', [OperatorCourtPricingRuleController::class, 'destroy'])->name('courts.pricing-rules.destroy');
    });

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', EnsureUserIsSuperadmin::class])
    ->group(function () {
        // Venue Management
        Route::get('/venues', [AdminVenueController::class, 'index'])->name('venues.index');
        Route::post('/venues', [AdminVenueController::class, 'store'])->name('venues.store');
        Route::get('/venues/{venue}', [AdminVenueController::class, 'show'])->name('venues.show');
        Route::patch('/venues/{venue}/subscription', [AdminVenueSubscriptionController::class, 'update'])->name('venues.subscription.update');

        // Venue Operator Assignment
        Route::get('/venues/{venue}/operators', [AdminVenueOperatorController::class, 'index'])->name('venues.operators.index');
        Route::post('/venues/{venue}/operators', [AdminVenueOperatorController::class, 'store'])->name('venues.operators.store');
        Route::delete('/venues/{venue}/operators/{user}', [AdminVenueOperatorController::class, 'destroy'])->name('venues.operators.destroy');

        // Master Data Curation: Sports
        Route::get('/sports', [AdminSportTypeController::class, 'index'])->name('sports.index');
        Route::post('/sports', [AdminSportTypeController::class, 'store'])->name('sports.store');
        Route::put('/sports/{sportType}', [AdminSportTypeController::class, 'update'])->name('sports.update');
        Route::delete('/sports/{sportType}', [AdminSportTypeController::class, 'destroy'])->name('sports.destroy');

        // Master Data Curation: Indonesian Cities
        Route::get('/cities', [AdminCityController::class, 'index'])->name('cities.index');
        Route::post('/cities', [AdminCityController::class, 'store'])->name('cities.store');
        Route::put('/cities/{city}', [AdminCityController::class, 'update'])->name('cities.update');
        Route::delete('/cities/{city}', [AdminCityController::class, 'destroy'])->name('cities.destroy');
    });
