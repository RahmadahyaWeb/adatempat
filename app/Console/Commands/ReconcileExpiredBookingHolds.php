<?php

namespace App\Console\Commands;

use App\Services\Payments\PaymentReconciliationService;
use Illuminate\Console\Command;

class ReconcileExpiredBookingHolds extends Command
{
    protected $signature = 'bookings:reconcile-expired-holds {--limit=100 : Maximum expired holds to process in one run}';

    protected $description = 'Reconcile expired booking holds against the payment gateway without releasing uncertain payments.';

    public function handle(PaymentReconciliationService $reconciliation): int
    {
        $limit = (int) $this->option('limit');
        $result = $reconciliation->reconcileExpiredHolds($limit);

        $this->info(sprintf(
            'Expired holds: %d scanned, %d released, %d requiring reconciliation, %d skipped.',
            $result['scanned'],
            $result['released'],
            $result['reconciling'],
            $result['skipped'],
        ));

        return self::SUCCESS;
    }
}
