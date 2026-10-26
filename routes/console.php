<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


Schedule::command('bookings:reconcile-expired-holds')
    ->everyMinute()
    ->withoutOverlapping();


Schedule::command('refunds:reconcile')
    ->everyFiveMinutes()
    ->withoutOverlapping();


Schedule::command('bookings:auto-complete-expired')
    ->everyMinute()
    ->withoutOverlapping();


Schedule::command('payouts:prepare-eligible')
    ->everyFiveMinutes()
    ->withoutOverlapping();


Schedule::command('subscriptions:process-lifecycle')
    ->hourly()
    ->withoutOverlapping();
