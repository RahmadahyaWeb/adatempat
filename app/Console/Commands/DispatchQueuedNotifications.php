<?php

namespace App\Console\Commands;

use App\Services\Notifications\NotificationDispatcherService;
use Illuminate\Console\Command;

class DispatchQueuedNotifications extends Command
{
    protected $signature = 'notifications:dispatch {--limit=100 : Maximum notification records to dispatch}';

    protected $description = 'Dispatch queued email and WhatsApp notifications with bounded retry.';

    public function handle(NotificationDispatcherService $dispatcher): int
    {
        $result = $dispatcher->dispatchQueued((int) $this->option('limit'));

        $this->info(sprintf(
            'Notifications: %d scanned, %d sent, %d failed, %d skipped.',
            $result['scanned'],
            $result['sent'],
            $result['failed'],
            $result['skipped'],
        ));

        return self::SUCCESS;
    }
}
