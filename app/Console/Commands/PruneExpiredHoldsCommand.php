<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\BookingConcurrencyService;
use Illuminate\Console\Command;

class PruneExpiredHoldsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'court-slots:prune-expired-holds';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prune expired checkout holds, transition abandoned draft bookings to expired, and reset slot statuses';

    /**
     * Execute the console command.
     */
    public function handle(BookingConcurrencyService $concurrencyService): int
    {
        $pruned = $concurrencyService->pruneExpiredHolds();

        $this->info(sprintf('%d expired checkout hold(s) pruned successfully.', $pruned));

        return Command::SUCCESS;
    }
}
