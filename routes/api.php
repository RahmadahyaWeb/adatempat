<?php

declare(strict_types=1);

use App\Http\Controllers\Public\PublicCourtSlotController;
use App\Http\Controllers\Public\PublicVenueController;
use Illuminate\Support\Facades\Route;

Route::prefix('venues')->name('venues.')->group(function () {
    Route::get('/', [PublicVenueController::class, 'index'])->name('index');
    Route::get('/{slug}', [PublicVenueController::class, 'show'])->name('show');
});

Route::prefix('courts')->name('courts.')->group(function () {
    Route::get('/{court}/slots', [PublicCourtSlotController::class, 'index'])->name('slots.index');
});
