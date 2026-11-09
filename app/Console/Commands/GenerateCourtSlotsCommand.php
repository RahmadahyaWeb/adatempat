<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\SlotGenerationService;
use Illuminate\Console\Command;

class GenerateCourtSlotsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'slots:generate
        {--days=30 : Number of rolling days ahead to generate}
        {--court= : Specific court ID to generate slots for}
        {--venue= : Specific venue ID to generate slots for}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate discrete 60-minute court slots across active courts within the booking horizon';

    /**
     * Execute the console command.
     */
    public function handle(SlotGenerationService $slotGenerationService): int
    {
        $days = (int) $this->option('days');
        $courtId = $this->option('court') !== null ? (int) $this->option('court') : null;
        $venueId = $this->option('venue') !== null ? (int) $this->option('venue') : null;

        $this->info(sprintf('Starting slot generation (days: %d)...', $days));

        $generated = $slotGenerationService->generateRollingSlots(
            daysAhead: $days,
            courtId: $courtId,
            venueId: $venueId
        );

        $this->info(sprintf('Generated %d new slots across courts.', $generated));

        return Command::SUCCESS;
    }
}
