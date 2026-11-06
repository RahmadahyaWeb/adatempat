<?php

namespace App\Console\Commands;

use App\Services\Bookings\BookingService;
use Illuminate\Console\Command;

class ExpirePendingBookingsCommand extends Command
{
    protected $signature = 'bookings:expire-pending';

    protected $description = 'Expire pending bookings whose 10-minute hold window has elapsed and release their slots.';

    public function handle(BookingService $bookingService): int
    {
        $count = $bookingService->expirePendingBookings();

        $this->info("Expired {$count} pending booking(s) and released held slots.");

        return self::SUCCESS;
    }
}
