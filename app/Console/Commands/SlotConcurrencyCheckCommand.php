<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SlotConcurrencyCheckCommand extends Command
{
    protected $signature = 'slot:concurrency-check
        {--space=9001 : Space ID used by the probe}
        {--date= : Slot date (default: tomorrow)}
        {--start=09:00 : Start time}
        {--end=10:00 : End time}
        {--booking-a=900001 : First booking ID}
        {--booking-b=900002 : Second booking ID}
        {--hold-ms=500 : How long the first transaction holds the row lock}';

    protected $description = 'Run two parallel MySQL reservations for the same canonical cell and assert exactly one wins.';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('Refusing to run the concurrency probe in production.');

            return self::FAILURE;
        }

        $spaceId = (int) $this->option('space');
        $date = (string) ($this->option('date') ?: now()->addDay()->toDateString());
        $start = (string) $this->option('start');
        $end = (string) $this->option('end');
        $bookingA = (int) $this->option('booking-a');
        $bookingB = (int) $this->option('booking-b');
        $holdMs = (int) $this->option('hold-ms');

        try {
            $db = DB::connection('mysql');
            $db->getPdo();
        } catch (Throwable $exception) {
            $this->error('MySQL tidak bisa dihubungi: '.$exception->getMessage());

            return self::FAILURE;
        }

        if (! Schema::connection('mysql')->hasTable('slot_locks')
            || ! Schema::connection('mysql')->hasTable('booking_slots')) {
            $this->error('Tabel belum ada. Jalankan `php artisan migrate` dulu.');

            return self::FAILURE;
        }

        $cleanup = function () use ($db, $spaceId, $date, $bookingA, $bookingB): void {
            $db->table('booking_slots')
                ->where('space_id', $spaceId)
                ->where('slot_date', $date)
                ->whereIn('booking_id', [$bookingA, $bookingB])
                ->delete();

            $db->table('slot_locks')
                ->where('space_id', $spaceId)
                ->where('slot_date', $date)
                ->delete();
        };

        $cleanup();

        $db->table('slot_locks')->insert([
            'space_id' => $spaceId,
            'slot_date' => $date,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $environment = [
            'APP_ENV' => 'local',
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => (string) config('database.connections.mysql.host'),
            'DB_PORT' => (string) config('database.connections.mysql.port'),
            'DB_DATABASE' => (string) config('database.connections.mysql.database'),
            'DB_USERNAME' => (string) config('database.connections.mysql.username'),
            'DB_PASSWORD' => (string) config('database.connections.mysql.password'),
            'DB_URL' => '',
        ];

        $process = fn (int $bookingId): array => [
            PHP_BINARY,
            'artisan',
            'slot:probe',
            (string) $bookingId,
            (string) $spaceId,
            $date,
            $start,
            $end,
            '--hold-ms='.$holdMs,
        ];

        $first = Process::path(base_path())->env($environment)->timeout(30)->start($process($bookingA));
        $second = Process::path(base_path())->env($environment)->timeout(30)->start($process($bookingB));

        $resultA = $first->wait();
        $resultB = $second->wait();

        $rows = $db->table('booking_slots')
            ->where('space_id', $spaceId)
            ->where('slot_date', $date)
            ->whereIn('booking_id', [$bookingA, $bookingB])
            ->get();

        $this->line('A exit='.$resultA->exitCode().' '.trim($resultA->output()));
        $this->line('B exit='.$resultB->exitCode().' '.trim($resultB->output()));
        $this->line('rows='.$rows->count().' booking_ids='.$rows->pluck('booking_id')->implode(','));

        $exitCodes = [$resultA->exitCode(), $resultB->exitCode()];
        sort($exitCodes);

        $passed = $exitCodes === [0, 1] && $rows->count() === 1;

        $cleanup();

        if (! $passed) {
            $this->error('FAIL: expected exactly one success, one conflict, and one persisted cell.');

            return self::FAILURE;
        }

        $this->info('PASS: exactly one reservation persisted; the other was rejected.');

        return self::SUCCESS;
    }
}
