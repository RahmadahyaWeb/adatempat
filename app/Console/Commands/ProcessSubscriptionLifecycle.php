<?php

namespace App\Console\Commands;

use App\Services\Subscriptions\SubscriptionLifecycleService;
use Illuminate\Console\Command;

class ProcessSubscriptionLifecycle extends Command
{
    protected $signature = 'subscriptions:process-lifecycle {--limit=200 : Maximum subscriptions to process per state transition}';

    protected $description = 'Activate prepaid scheduled downgrades, start grace periods, and suspend expired subscriptions.';

    public function handle(SubscriptionLifecycleService $lifecycle): int
    {
        $result = $lifecycle->processDue((int) $this->option('limit'));

        $this->info(sprintf(
            'Subscriptions: %d scheduled downgrades activated, %d entered grace, %d suspended, %d skipped.',
            $result['downgraded'],
            $result['grace_started'],
            $result['suspended'],
            $result['skipped'],
        ));

        return self::SUCCESS;
    }
}
