<?php

use App\Http\Controllers\Auth\RegisteredBusinessController;
use App\Http\Controllers\Catalog\CheckoutController;
use App\Http\Controllers\Catalog\HomeController;
use App\Http\Controllers\Catalog\PaymentController;
use App\Http\Controllers\Catalog\SpaceBrowseController;
use App\Http\Controllers\Catalog\SpaceController;
use App\Http\Controllers\Catalog\SpaceSlotController;
use App\Http\Controllers\Catalog\TicketController;
use App\Http\Controllers\Catalog\VenueController;
use App\Http\Controllers\Catalog\VenueCoverController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Gate\GateScannerController;
use App\Http\Controllers\Owner\BusinessController as OwnerBusinessController;
use App\Http\Controllers\Owner\SpaceAddOnController as OwnerSpaceAddOnController;
use App\Http\Controllers\Owner\SpaceController as OwnerSpaceController;
use App\Http\Controllers\Owner\SpaceScheduleController as OwnerSpaceScheduleController;
use App\Http\Controllers\Owner\VenueController as OwnerVenueController;
use App\Http\Controllers\Superadmin\AuditLogController as SuperadminAuditLogController;
use App\Http\Controllers\Superadmin\BusinessController as SuperadminBusinessController;
use App\Http\Controllers\Superadmin\VenueApprovalController as SuperadminVenueApprovalController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('spaces', [SpaceBrowseController::class, 'index'])->name('spaces.index');
Route::get('spaces/{space}', [SpaceController::class, 'show'])->name('spaces.show');
Route::get('spaces/{space}/slots', [SpaceSlotController::class, 'index'])->name('spaces.slots');
Route::get('v/{venue}', [VenueController::class, 'show'])->name('venues.show');
Route::get('tickets/{code}', [TicketController::class, 'show'])->name('tickets.show');
Route::get('venues/{venue}/cover', [VenueCoverController::class, 'show'])->name('venues.cover');

Route::middleware(['auth'])->group(function () {
    Route::get('checkout', [CheckoutController::class, 'create'])->name('checkout.create');
    Route::post('checkout', [CheckoutController::class, 'store'])->name('checkout.store');
    Route::get('payment/{code}', [PaymentController::class, 'show'])->name('payment.show');
    Route::get('payment/{code}/status', [PaymentController::class, 'status'])->name('payment.status');
    Route::post('payment/{code}/simulate', [PaymentController::class, 'simulate'])->name('payment.simulate');
});

Route::middleware('guest')->group(function () {
    Route::get('register/business', [RegisteredBusinessController::class, 'create'])->name('register.business');
    Route::post('register/business', [RegisteredBusinessController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('register.business.store');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::middleware('role:owner,superadmin')->prefix('owner')->name('owner.')->group(function () {
        Route::get('businesses', [OwnerBusinessController::class, 'index'])->name('businesses.index');
        Route::get('businesses/{business}', [OwnerBusinessController::class, 'show'])->name('businesses.show');
        Route::get('venues', [OwnerVenueController::class, 'index'])->name('venues.index');
        Route::get('venues/create', [OwnerVenueController::class, 'create'])->name('venues.create');
        Route::post('venues', [OwnerVenueController::class, 'store'])->name('venues.store');
        Route::get('venues/{venue}/edit', [OwnerVenueController::class, 'edit'])->name('venues.edit');
        Route::put('venues/{venue}', [OwnerVenueController::class, 'update'])->name('venues.update');
        Route::delete('venues/{venue}', [OwnerVenueController::class, 'destroy'])->name('venues.destroy');
        Route::post('venues/{venue}/publish', [OwnerVenueController::class, 'togglePublish'])->name('venues.publish');
        Route::post('venues/{venue}/submit-approval', [OwnerVenueController::class, 'submitApproval'])->name('venues.submit-approval');

        // Space Management
        Route::get('venues/{venue}/spaces', [OwnerSpaceController::class, 'index'])->name('venues.spaces.index');
        Route::get('venues/{venue}/spaces/create', [OwnerSpaceController::class, 'create'])->name('venues.spaces.create');
        Route::post('venues/{venue}/spaces', [OwnerSpaceController::class, 'store'])->name('venues.spaces.store');
        Route::get('venues/{venue}/spaces/{space}/edit', [OwnerSpaceController::class, 'edit'])->name('venues.spaces.edit');
        Route::put('venues/{venue}/spaces/{space}', [OwnerSpaceController::class, 'update'])->name('venues.spaces.update');
        Route::delete('venues/{venue}/spaces/{space}', [OwnerSpaceController::class, 'destroy'])->name('venues.spaces.destroy');

        // Schedules
        Route::get('venues/{venue}/spaces/{space}/schedules', [OwnerSpaceScheduleController::class, 'index'])->name('venues.spaces.schedules.index');
        Route::put('venues/{venue}/spaces/{space}/schedules', [OwnerSpaceScheduleController::class, 'update'])->name('venues.spaces.schedules.update');

        // Add-Ons
        Route::get('venues/{venue}/spaces/{space}/add-ons', [OwnerSpaceAddOnController::class, 'index'])->name('venues.spaces.add-ons.index');
        Route::post('venues/{venue}/spaces/{space}/add-ons', [OwnerSpaceAddOnController::class, 'store'])->name('venues.spaces.add-ons.store');
        Route::put('venues/{venue}/spaces/{space}/add-ons/{addOn}', [OwnerSpaceAddOnController::class, 'update'])->name('venues.spaces.add-ons.update');
        Route::delete('venues/{venue}/spaces/{space}/add-ons/{addOn}', [OwnerSpaceAddOnController::class, 'destroy'])->name('venues.spaces.add-ons.destroy');
    });

    Route::middleware(['role:superadmin', 'superadmin.2fa'])->prefix('superadmin')->name('superadmin.')->group(function () {
        Route::get('businesses/{business}', [SuperadminBusinessController::class, 'show'])->name('businesses.show');
        Route::get('venues', [SuperadminVenueApprovalController::class, 'index'])->name('venues.index');
        Route::get('venues/{venue}', [SuperadminVenueApprovalController::class, 'show'])->name('venues.show');
        Route::post('venues/{venue}/approve', [SuperadminVenueApprovalController::class, 'approve'])->name('venues.approve');
        Route::post('venues/{venue}/reject', [SuperadminVenueApprovalController::class, 'reject'])->name('venues.reject');
        Route::post('venues/{venue}/unpublish', [SuperadminVenueApprovalController::class, 'unpublish'])->name('venues.unpublish');
        Route::get('audit', [SuperadminAuditLogController::class, 'index'])->name('audit.index');
    });

    Route::middleware('role:owner,superadmin')->prefix('gate')->name('gate.')->group(function () {
        Route::get('scanner', [GateScannerController::class, 'show'])->name('scanner');
        Route::post('scanner/scan', [GateScannerController::class, 'scan'])->name('scanner.scan');
    });
});

require __DIR__.'/settings.php';
