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
        $limit = (int) $this->option('limit');
        $advanced = $completion->advanceEndedBookings($limit);
        $count = $completion->autoCompleteExpired($limit);
        $this->info(sprintf('Advanced %d and completed %d booking(s).', $advanced, $count));

        return self::SUCCESS;
    }
}
