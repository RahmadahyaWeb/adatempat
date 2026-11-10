<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\CheckInVerificationService;
use Illuminate\Console\Command;

class MarkCompletedBookingsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bookings:mark-completed';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Periodically transition reservations whose court slot operational hours have completely passed into completed status';

    /**
     * Execute the console command.
     */
    public function handle(CheckInVerificationService $verificationService): int
    {
        $count = $verificationService->markElapsedBookingsCompleted();

        $this->info(sprintf('Marked %d elapsed booking(s) as completed.', $count));

        return Command::SUCCESS;
    }
}
