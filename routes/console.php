<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('slots:generate --days=30')->daily();
Schedule::command('court-slots:prune-expired-holds')->everyMinute();
Schedule::command('bookings:mark-completed')->hourly();
