<?php

namespace App\Console\Commands;

use App\Services\Finance\PayoutService;
use Illuminate\Console\Command;

class PrepareEligiblePayouts extends Command
{
    protected $signature = 'payouts:prepare-eligible {--limit=100 : Maximum completed bookings to inspect}';

    protected $description = 'Prepare owner payouts after the completion window closes.';

    public function handle(PayoutService $payouts): int
    {
        $result = $payouts->prepareEligiblePayouts((int) $this->option('limit'));

        $this->info(sprintf(
            'Payouts: %d created, %d eligible, %d held, %d zero-value/cancelled, %d skipped.',
            $result['created'],
            $result['eligible'],
            $result['held'],
            $result['cancelled'],
            $result['skipped'],
        ));

        return self::SUCCESS;
    }
}
