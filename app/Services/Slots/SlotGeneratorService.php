<?php

namespace App\Services\Slots;

use App\Models\Space;
use App\Models\SpaceScheduleVersion;
use App\Services\Bookings\BookingService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SlotGeneratorService
{
    /**
     * Generate canonical grid slots dynamically for a given space and date.
     *
     * In-memory computation based on active schedule version, venue timezone,
     * 30-day horizon, and database diff against booking_slots.
     *
     * @return array{
     *     date: string,
     *     timezone: string,
     *     slot_duration_minutes: int|null,
     *     slots: array<int, array{
     *         start_time: string,
     *         end_time: string,
     *         price: int,
     *         status: 'available'|'booked'|'past'
     *     }>
     * }
     */
    public function generate(
        Space $space,
        string $date,
        ?CarbonInterface $now = null,
    ): array {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new InvalidArgumentException('Format tanggal harus Y-m-d.');
        }

        if (! $space->relationLoaded('venue')) {
            $space->load('venue');
        }

        $timezone = $space->venue->timezone ?? config('app.timezone', 'Asia/Jakarta');

        $currentNow = $now !== null
            ? CarbonImmutable::instance($now)->setTimezone($timezone)
            : CarbonImmutable::now($timezone);

        $today = $currentNow->startOfDay();
        $targetDate = CarbonImmutable::parse($date, $timezone)->startOfDay();

        if ($targetDate->lt($today)) {
            throw new InvalidArgumentException('Tanggal tidak boleh di masa lampau.');
        }

        $maxHorizon = $today->addDays(30);
        if ($targetDate->gt($maxHorizon)) {
            throw new InvalidArgumentException('Tanggal melebihi batas horizon 30 hari.');
        }

        $version = SpaceScheduleVersion::query()
            ->where('space_id', $space->id)
            ->where('effective_from', '<=', $date)
            ->where(function ($query) use ($date): void {
                $query->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $date);
            })
            ->orderByDesc('version')
            ->first();

        if ($version === null) {
            return [
                'date' => $date,
                'timezone' => $timezone,
                'slot_duration_minutes' => null,
                'slots' => [],
            ];
        }

        $dayOfWeek = $targetDate->dayOfWeek;
        $schedules = $version->schedules()
            ->where('day_of_week', $dayOfWeek)
            ->where('is_active', true)
            ->orderBy('open_time')
            ->get();

        if ($schedules->isEmpty()) {
            return [
                'date' => $date,
                'timezone' => $timezone,
                'slot_duration_minutes' => null,
                'slots' => [],
            ];
        }
        // Expire past-due pending holds on the fly before checking booked slots
        app(BookingService::class)->expirePendingBookings($currentNow, $space->id, $date);

        /** @var array<string, int> $bookedStartTimes */
        $bookedStartTimes = DB::table('booking_slots')
            ->where('space_id', $space->id)
            ->where('slot_date', $date)
            ->pluck('start_time')
            ->map(fn (mixed $time): string => substr((string) $time, 0, 5))
            ->flip()
            ->all();
        $slots = [];
        $primaryDuration = null;
        $isToday = $targetDate->isSameDay($today);
        $currentTimeString = $currentNow->format('H:i');

        foreach ($schedules as $schedule) {
            $duration = $schedule->slot_duration_minutes;
            if ($duration <= 0) {
                continue;
            }

            if ($primaryDuration === null) {
                $primaryDuration = $duration;
            }

            $slotPrice = (int) round(((float) $space->base_price / 60) * $duration);

            $openTime = substr((string) $schedule->open_time, 0, 5);
            $closeTime = substr((string) $schedule->close_time, 0, 5);

            $startDt = CarbonImmutable::parse("{$date} {$openTime}:00", $timezone);
            $endDt = CarbonImmutable::parse("{$date} {$closeTime}:00", $timezone);

            $cursor = $startDt;
            while ($cursor->addMinutes($duration)->lte($endDt)) {
                $next = $cursor->addMinutes($duration);
                $startStr = $cursor->format('H:i');
                $endStr = $next->format('H:i');

                if (isset($bookedStartTimes[$startStr])) {
                    $status = 'booked';
                } elseif ($isToday && $startStr <= $currentTimeString) {
                    $status = 'past';
                } else {
                    $status = 'available';
                }

                $slots[] = [
                    'start_time' => $startStr,
                    'end_time' => $endStr,
                    'price' => $slotPrice,
                    'status' => $status,
                ];

                $cursor = $next;
            }
        }

        return [
            'date' => $date,
            'timezone' => $timezone,
            'slot_duration_minutes' => $primaryDuration,
            'slots' => $slots,
        ];
    }
}
