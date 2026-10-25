
<?php

use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::livewire('/', 'pages::home')->name('home');

Route::livewire('/register', 'pages::auth.register')
    ->middleware('guest')
    ->name('register');

Route::livewire('/login', 'pages::auth.login')
    ->middleware('guest')
    ->name('login');

/*
|--------------------------------------------------------------------------
| Verifikasi Email
|--------------------------------------------------------------------------
*/

Route::livewire('/email/verify', 'pages::auth.verify-email')
    ->middleware(['auth', 'account.active'])
    ->name('verification.notice');

Route::get('/email/verify/{id}/{hash}', function (
    EmailVerificationRequest $request
) {
    $request->fulfill();

    return redirect()->route('home');
})->middleware([
    'auth',
    'account.active',
    'signed',
    'throttle:6,1',
])->name('verification.verify');

Route::post('/email/verification-notification', function (
    Request $request
) {
    if ($request->user()->hasVerifiedEmail()) {
        return redirect()->route('home');
    }

    $request->user()->sendEmailVerificationNotification();

    return back()->with(
        'status',
        'Tautan verifikasi baru telah dikirim.'
    );
})->middleware([
    'auth',
    'account.active',
    'throttle:6,1',
])->name('verification.send');

/*
|--------------------------------------------------------------------------
| Area Super Admin
|--------------------------------------------------------------------------
|
| Semua rute dashboard dan fitur admin harus didefinisikan
| di dalam grup ini agar mewarisi middleware keamanan.
|
*/

Route::prefix('admin')
    ->middleware([
        'auth',
        'account.active',
        'role:super_admin',
        'admin.mfa',
    ])
    ->group(function () {
        Route::livewire('/mfa/setup', 'pages::admin.mfa.setup')
            ->name('mfa.setup');

        Route::livewire('/mfa/challenge', 'pages::admin.mfa.challenge')
            ->name('mfa.challenge');

        /*
         * Tambahkan rute dashboard dan fitur Super Admin
         * di dalam grup ini saat fitur tersebut dibuat.
         */
    });

/*
|--------------------------------------------------------------------------
| Logout
|--------------------------------------------------------------------------
*/

Route::post('/logout', function (Request $request) {
    Auth::guard('web')->logout();

    $request->session()->forget([
        'admin_mfa_verified_user_id',
        'admin_mfa_verified_at',
    ]);

    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->route('home');
})->middleware('auth')->name('logout');

/*
|--------------------------------------------------------------------------
| Password Reset
|--------------------------------------------------------------------------
*/

Route::livewire('/forgot-password', 'pages::auth.forgot-password')
    ->middleware('guest')
    ->name('password.request');

Route::livewire('/reset-password/{token}', 'pages::auth.reset-password')
    ->middleware('guest')
    ->name('password.reset');


/*
|--------------------------------------------------------------------------
| Area Owner
|--------------------------------------------------------------------------
|
| Akses pengelolaan usaha hanya tersedia untuk owner yang aktif dan telah
| memverifikasi email. Setiap halaman tetap melakukan query berbasis owner_id.
|
*/

Route::prefix('owner')
    ->name('owner.')
    ->middleware([
        'auth',
        'account.active',
        'verified',
        'role:owner',
    ])
    ->group(function () {
        Route::livewire('/businesses', 'pages::owner.businesses')
            ->name('businesses.index');

        Route::livewire('/businesses/{business}/venues', 'pages::owner.venues')
            ->whereNumber('business')
            ->name('businesses.venues.index');

        Route::livewire('/businesses/{business}/venues/create', 'pages::owner.venue-form')
            ->whereNumber('business')
            ->name('businesses.venues.create');

        Route::livewire('/businesses/{business}/venues/{venue}/edit', 'pages::owner.venue-form')
            ->whereNumber('business')
            ->whereNumber('venue')
            ->name('businesses.venues.edit');

        Route::livewire('/businesses/{business}/edit', 'pages::owner.business-edit')
            ->whereNumber('business')
            ->name('businesses.edit');
    });


/*
|--------------------------------------------------------------------------
| Halaman publik usaha
|--------------------------------------------------------------------------
|
| Letakkan rute slug di bagian akhir agar rute statis seperti login,
| registrasi, dan pemulihan password tetap menjadi prioritas.
|
*/

Route::livewire('/{businessSlug}', 'pages::business')
    ->where('businessSlug', '[a-z0-9-]+')
    ->name('business.public');
