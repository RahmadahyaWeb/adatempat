<?php

namespace App\Services\Slots;

use App\Exceptions\SlotConflictException;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SlotReservationService
{
    /**
     * Reserve one or more canonical grid cells for a booking.
     *
     * The caller must pass every cell covered by the booking range, aligned to
     * the space's slot grid. All cells are inserted in one transaction, so a
     * conflict on any cell rolls back the whole booking.
     *
     * @param  array<int, array{start_time: string, end_time: string, price?: int|float|string}>  $cells
     */
    public function reserve(
        int $bookingId,
        int $spaceId,
        string $slotDate,
        array $cells,
        int $holdMilliseconds = 0,
    ): void {
        $cells = $this->normalize($slotDate, $cells);

        try {
            Cache::lock("lock:slot_reserve:{$spaceId}:{$slotDate}", 10)->block(5, function () use (
                $bookingId,
                $spaceId,
                $slotDate,
                $cells,
                $holdMilliseconds,
            ): void {
                DB::transaction(function () use (
                    $bookingId,
                    $spaceId,
                    $slotDate,
                    $cells,
                    $holdMilliseconds,
                ): void {
                    $startTimes = array_column($cells, 'start_time');
                    $conflictExists = DB::table('booking_slots')
                        ->where('space_id', $spaceId)
                        ->where('slot_date', $slotDate)
                        ->whereIn('start_time', $startTimes)
                        ->exists();

                    if ($conflictExists) {
                        throw new SlotConflictException('Slot sudah dipesan.');
                    }

                    DB::table('slot_locks')->insertOrIgnore([
                        'space_id' => $spaceId,
                        'slot_date' => $slotDate,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    DB::table('slot_locks')
                        ->where('space_id', $spaceId)
                        ->where('slot_date', $slotDate)
                        ->lockForUpdate()
                        ->first();

                    if ($holdMilliseconds > 0) {
                        usleep($holdMilliseconds * 1000);
                    }

                    foreach ($cells as $cell) {
                        DB::table('booking_slots')->insert([
                            'booking_id' => $bookingId,
                            'space_id' => $spaceId,
                            'slot_date' => $slotDate,
                            'start_time' => $cell['start_time'],
                            'end_time' => $cell['end_time'],
                            'price' => $cell['price'] ?? 0,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }, 3);
            });

            Cache::forget("slots:space:{$spaceId}:date:{$slotDate}");
        } catch (LockTimeoutException $exception) {
            throw new SlotConflictException('Sistem sedang memproses pemesanan pada jadwal ini. Silakan coba kembali sesaat lagi.', previous: $exception);
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() === '23000') {
                throw new SlotConflictException('Slot sudah dipesan.', previous: $exception);
            }

            throw $exception;
        }
    }

    /**
     * @param  array<int, array{start_time: string, end_time: string, price?: int|float|string}>  $cells
     * @return array<int, array{start_time: string, end_time: string, price?: int|float|string}>
     */
    private function normalize(string $slotDate, array $cells): array
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $slotDate)) {
            throw new InvalidArgumentException('slot_date harus berformat Y-m-d.');
        }

        if ($cells === []) {
            throw new InvalidArgumentException('Minimal satu cell harus diisi.');
        }

        $normalized = [];

        foreach ($cells as $cell) {
            if (! preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $cell['start_time'])
                || ! preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $cell['end_time'])) {
                throw new InvalidArgumentException('start_time/end_time harus berformat H:i atau H:i:s.');
            }

            $start = strlen($cell['start_time']) === 5 ? $cell['start_time'].':00' : $cell['start_time'];
            $end = strlen($cell['end_time']) === 5 ? $cell['end_time'].':00' : $cell['end_time'];

            if ($start >= $end) {
                throw new InvalidArgumentException('start_time harus lebih kecil dari end_time.');
            }

            $normalized[] = [
                'start_time' => $start,
                'end_time' => $end,
                'price' => $cell['price'] ?? 0,
            ];
        }

        usort($normalized, fn (array $a, array $b): int => strcmp($a['start_time'], $b['start_time']));

        return $normalized;
    }
}
