<?php

namespace App\Jobs\Bookings;

use App\Services\Bookings\BookingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExpirePendingBookingsJob implements ShouldQueue
{
    use Queueable;

    public function handle(BookingService $bookingService): void
    {
        $bookingService->expirePendingBookings();
    }
}
