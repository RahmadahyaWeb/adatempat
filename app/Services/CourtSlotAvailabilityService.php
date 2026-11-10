<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BookingHorizonExceededException;
use App\Models\Court;
use App\Models\CourtSlot;
use App\Models\Venue;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CourtSlotAvailabilityService
{
    public function __construct(
        protected SlotGenerationService $slotGenerationService
    ) {}

    /**
     * Validate that a target date falls within the venue's active booking horizon.
     *
     * @throws BookingHorizonExceededException
     */
    public function validateBookingHorizon(Venue $venue, CarbonInterface|string $date): CarbonInterface
    {
        return $this->slotGenerationService->validateBookingHorizon($venue, $date);
    }

    /**
     * Retrieve chronological 60-minute slot availability for a specific court and calendar date.
     * Enforces booking horizon, generates slots just-in-time, executes specialized covering index scan,
     * and evaluates dynamic status and hold expirations.
     *
     * @return array<int, array{id: int, court_id: int, date: string, start_time: string, end_time: string, price: int, status: string, is_bookable: bool}>
     *
     * @throws BookingHorizonExceededException
     */
    public function getAvailableSlots(Court $court, CarbonInterface|string $date): array
    {
        $venue = $court->relationLoaded('venue') ? $court->venue : $court->venue()->firstOrFail();
        $targetDate = $this->validateBookingHorizon($venue, $date);
        $dateString = $targetDate->toDateString();

        // Ensure missing slots are generated just-in-time
        $this->slotGenerationService->generateSlotsForCourtAndDate($court, $targetDate);

        // Utilize the specialized covering index idx_slots_availability for an index-only scan
        $slots = CourtSlot::query()
            ->select(['id', 'court_id', 'date', 'status', 'start_time', 'end_time', 'price', 'locked_until'])
            ->from(DB::raw('court_slots FORCE INDEX (idx_slots_availability)'))
            ->where('court_id', $court->id)
            ->where('date', $dateString)
            ->orderBy('start_time', 'asc')
            ->get();

        $now = now();

        return $slots->map(fn (CourtSlot $slot): array => [
            'id' => $slot->id,
            'court_id' => $slot->court_id,
            'date' => is_object($slot->date) ? $slot->date->format('Y-m-d') : (string) $slot->date,
            'start_time' => (string) $slot->start_time,
            'end_time' => (string) $slot->end_time,
            'price' => (int) $slot->price,
            'status' => $slot->resolveEffectiveStatus($now)->value,
            'is_bookable' => $slot->isBookable($now),
        ])->all();
    }

    /**
     * Retrieve court slots for an operator query, generating missing slots just-in-time.
     *
     * @return Collection<int, CourtSlot>
     *
     * @throws BookingHorizonExceededException
     */
    public function getSlotsForCourt(Court $court, CarbonInterface|string $date): Collection
    {
        $venue = $court->relationLoaded('venue') ? $court->venue : $court->venue()->firstOrFail();
        $targetDate = $this->validateBookingHorizon($venue, $date);

        return $this->slotGenerationService->generateSlotsForCourtAndDate($court, $targetDate);
    }

    /**
     * Retrieve court slots for multiple courts grouped by court_id for operator timeline display,
     * ensuring JIT generation across all specified courts.
     *
     * @param  Collection<int, Court>  $courts
     * @return Collection<(int|string), Collection<int, CourtSlot>>
     *
     * @throws BookingHorizonExceededException
     */
    public function getTimelineSlotsForCourts(Venue $venue, Collection $courts, CarbonInterface|string $date): Collection
    {
        $targetDate = $this->validateBookingHorizon($venue, $date);
        $dateString = $targetDate->toDateString();

        foreach ($courts as $court) {
            $this->slotGenerationService->generateSlotsForCourtAndDate($court, $targetDate);
        }

        $courtIds = $courts->pluck('id')->all();

        return CourtSlot::query()
            ->whereIn('court_id', $courtIds)
            ->where('date', $dateString)
            ->with([
                'bookingItems.booking.user',
            ])
            ->orderBy('start_time')
            ->get()
            ->toBase()
            ->groupBy('court_id');
    }
}
