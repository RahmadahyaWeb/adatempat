<?php

namespace App\Console\Commands;

use App\Services\Payments\RefundReconciliationService;
use Illuminate\Console\Command;

class ReconcileRefunds extends Command
{
    protected $signature = 'refunds:reconcile {--limit=100 : Maximum refund records to inspect in one run}';

    protected $description = 'Retry failed refund requests and query refunds still processing.';

    public function handle(RefundReconciliationService $reconciliation): int
    {
        $result = $reconciliation->reconcile((int) $this->option('limit'));

        $this->info(sprintf(
            'Refunds: %d scanned, %d succeeded, %d processing, %d failed, %d skipped.',
            $result['scanned'],
            $result['succeeded'],
            $result['processing'],
            $result['failed'],
            $result['skipped'],
        ));

        return self::SUCCESS;
    }
}
