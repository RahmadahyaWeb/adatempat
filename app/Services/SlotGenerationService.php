<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SlotStatus;
use App\Exceptions\BookingHorizonExceededException;
use App\Models\Court;
use App\Models\CourtSlot;
use App\Models\Venue;
use App\Support\TimeHelper;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class SlotGenerationService
{
    public function __construct(
        protected PricingResolutionService $pricingResolutionService
    ) {}

    /**
     * Validate that a target date falls within the venue's active booking horizon.
     *
     * @throws BookingHorizonExceededException
     */
    public function validateBookingHorizon(Venue $venue, CarbonInterface|string $date): CarbonInterface
    {
        $targetDate = is_string($date) ? Carbon::parse($date)->startOfDay() : $date->copy()->startOfDay();
        $today = now()->startOfDay();

        if ($targetDate->lt($today)) {
            throw new BookingHorizonExceededException('Court slots cannot be queried or generated for past dates.');
        }

        $horizonLimit = $today->copy()->addDays((int) $venue->booking_horizon_days);
        if ($targetDate->gt($horizonLimit)) {
            throw new BookingHorizonExceededException(
                sprintf('Date %s exceeds venue booking horizon of %d days.', $targetDate->toDateString(), $venue->booking_horizon_days)
            );
        }

        return $targetDate;
    }

    /**
     * Generate discrete 60-minute court slots idempotently for a specific court and date.
     *
     * @return Collection<int, CourtSlot>
     *
     * @throws BookingHorizonExceededException
     */
    public function generateSlotsForCourtAndDate(Court $court, CarbonInterface|string $date): Collection
    {
        $venue = $court->relationLoaded('venue') ? $court->venue : $court->venue()->firstOrFail();
        $targetDate = $this->validateBookingHorizon($venue, $date);
        $dateString = $targetDate->toDateString();

        $openingParts = explode(':', (string) $court->opening_time);
        $startHour = (int) $openingParts[0];
        if (((int) ($openingParts[1] ?? 0) > 0) || ((int) ($openingParts[2] ?? 0) > 0)) {
            $startHour += 1;
        }

        $closingParts = explode(':', (string) $court->closing_time);
        $endHour = (int) $closingParts[0];

        // Retrieve existing slots for this court and date
        $existingSlots = CourtSlot::where('court_id', $court->id)
            ->where('date', $dateString)
            ->get()
            ->keyBy(fn (CourtSlot $slot) => TimeHelper::normalize((string) $slot->start_time));

        $newSlotsToInsert = [];
        $nowTimestamp = now();

        for ($hour = $startHour; $hour < $endHour; $hour++) {
            $slotStart = sprintf('%02d:00:00', $hour);
            $slotEnd = sprintf('%02d:00:00', $hour + 1);

            if ($existingSlots->has($slotStart)) {
                continue;
            }

            $price = $this->pricingResolutionService->resolveSlotPrice(
                $court,
                $targetDate,
                $slotStart,
                $slotEnd
            );

            $newSlotsToInsert[] = [
                'court_id' => $court->id,
                'date' => $dateString,
                'start_time' => $slotStart,
                'end_time' => $slotEnd,
                'price' => $price,
                'status' => SlotStatus::Available->value,
                'locked_until' => null,
                'block_reason' => null,
                'created_at' => $nowTimestamp,
                'updated_at' => $nowTimestamp,
            ];
        }

        if (! empty($newSlotsToInsert)) {
            CourtSlot::insertOrIgnore($newSlotsToInsert);
        }

        return CourtSlot::where('court_id', $court->id)
            ->where('date', $dateString)
            ->orderBy('start_time')
            ->get();
    }

    /**
     * Ahead-of-time batch generation for rolling window across active courts.
     *
     * @return int Total number of slots inserted
     */
    public function generateRollingSlots(int $daysAhead = 30, ?int $courtId = null, ?int $venueId = null): int
    {
        $venuesQuery = Venue::where('is_active', true);
        if ($venueId !== null) {
            $venuesQuery->where('id', $venueId);
        }

        $venues = $venuesQuery->with(['courts' => function ($query) use ($courtId) {
            $query->where('is_active', true);
            if ($courtId !== null) {
                $query->where('id', $courtId);
            }
            $query->with('pricingRules');
        }])->get();

        $totalInserted = 0;
        $today = now()->startOfDay();

        foreach ($venues as $venue) {
            $effectiveDays = min($daysAhead, (int) $venue->booking_horizon_days);

            foreach ($venue->courts as $court) {
                for ($day = 0; $day <= $effectiveDays; $day++) {
                    $targetDate = $today->copy()->addDays($day);
                    $beforeCount = CourtSlot::where('court_id', $court->id)
                        ->where('date', $targetDate->toDateString())
                        ->count();

                    $this->generateSlotsForCourtAndDate($court, $targetDate);

                    $afterCount = CourtSlot::where('court_id', $court->id)
                        ->where('date', $targetDate->toDateString())
                        ->count();

                    $totalInserted += ($afterCount - $beforeCount);
                }
            }
        }

        return $totalInserted;
    }
}
