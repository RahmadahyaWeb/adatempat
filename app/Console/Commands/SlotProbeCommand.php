<?php

namespace App\Console\Commands;

use App\Exceptions\SlotConflictException;
use App\Services\Slots\SlotReservationService;
use Illuminate\Console\Command;
use Throwable;

class SlotProbeCommand extends Command
{
    protected $signature = 'slot:probe
        {booking : Booking ID}
        {space : Space ID}
        {date : Slot date (Y-m-d)}
        {start : Start time (H:i or H:i:s)}
        {end : End time (H:i or H:i:s)}
        {--price=100000 : Cell price}
        {--hold-ms=0 : Hold the DB transaction open for N milliseconds}';

    protected $description = 'Attempt to reserve one canonical slot cell (used by the concurrency probe).';

    public function handle(SlotReservationService $service): int
    {
        try {
            $service->reserve(
                (int) $this->argument('booking'),
                (int) $this->argument('space'),
                (string) $this->argument('date'),
                [[
                    'start_time' => (string) $this->argument('start'),
                    'end_time' => (string) $this->argument('end'),
                    'price' => (int) $this->option('price'),
                ]],
                (int) $this->option('hold-ms'),
            );
        } catch (SlotConflictException $exception) {
            $this->warn('CONFLICT '.$exception->getMessage());

            return self::FAILURE;
        } catch (Throwable $exception) {
            $this->error('ERROR '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info('OK booking='.$this->argument('booking').' slot='.$this->argument('date').' '.$this->argument('start'));

        return self::SUCCESS;
    }
}
