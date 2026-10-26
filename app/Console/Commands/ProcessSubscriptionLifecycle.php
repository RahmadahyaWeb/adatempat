<?php

namespace App\Console\Commands;

use App\Services\Subscriptions\SubscriptionLifecycleService;
use Illuminate\Console\Command;

class ProcessSubscriptionLifecycle extends Command
{
    protected $signature = 'subscriptions:process-lifecycle {--limit=200 : Maximum subscriptions to process per state transition}';

    protected $description = 'Start subscription grace periods and suspend subscriptions after grace expires.';

    public function handle(SubscriptionLifecycleService $lifecycle): int
    {
        $result = $lifecycle->processDue((int) $this->option('limit'));

        $this->info(sprintf(
            'Subscriptions: %d entered grace period, %d suspended, %d skipped.',
            $result['grace_started'],
            $result['suspended'],
            $result['skipped'],
        ));

        return self::SUCCESS;
    }
}
