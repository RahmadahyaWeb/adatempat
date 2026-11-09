<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Court;
use Carbon\CarbonInterface;

class SlotPricingPropagationService
{
    public function __construct(
        protected PricingResolutionService $pricingResolutionService
    ) {}

    /**
     * Propagate active pricing rules to future available slots for a court.
     * Held, booked, and blocked slots are left untouched.
     *
     * @return int Number of slots whose prices were updated
     */
    public function propagatePricingRules(Court $court, ?CarbonInterface $now = null): int
    {
        $court->load('pricingRules');

        $futureAvailableSlots = $court->slots()
            ->available()
            ->future($now)
            ->get();

        $updatedCount = 0;

        foreach ($futureAvailableSlots as $slot) {
            $newPrice = $this->pricingResolutionService->resolveSlotPrice(
                $court,
                $slot->date,
                (string) $slot->start_time,
                (string) $slot->end_time
            );

            if ($newPrice !== (int) $slot->price) {
                $slot->update(['price' => $newPrice]);
                $updatedCount++;
            }
        }

        return $updatedCount;
    }
}
