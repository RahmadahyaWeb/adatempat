<?php

namespace App\Console\Commands;

use App\Services\Booking\BookingCompletionService;
use Illuminate\Console\Command;

class AutoCompleteExpiredBookings extends Command
{
    protected $signature = 'bookings:auto-complete-expired {--limit=100 : Maximum bookings to complete in one run}';

    protected $description = 'Automatically complete bookings after the 24-hour customer confirmation window expires.';

    public function handle(BookingCompletionService $completion): int
    {
        $count = $completion->autoCompleteExpired((int) $this->option('limit'));
        $this->info(sprintf('Automatically completed %d booking(s).', $count));

        return self::SUCCESS;
    }
}
