<?php

use App\Http\Controllers\Auth\MagicLinkController;
use App\Http\Controllers\HealthcheckController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/health', HealthcheckController::class)->name('health');

Route::prefix('auth')->name('auth.')->group(function () {
    Route::post('/magic-link', [MagicLinkController::class, 'send'])->name('magic-link.send');
    Route::get('/magic-link/verify/{user}', [MagicLinkController::class, 'verify'])->name('magic-link.verify');
});
