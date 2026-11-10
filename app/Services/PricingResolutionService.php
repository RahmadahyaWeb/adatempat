<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DayType;
use App\Models\Court;
use App\Models\CourtPricingRule;
use App\Support\TimeHelper;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class PricingResolutionService
{
    /**
     * Resolve slot price for a given court, date, and discrete time interval.
     */
    public function resolveSlotPrice(
        Court $court,
        CarbonInterface|string $date,
        string $startTime,
        string $endTime
    ): int {
        $rule = $this->resolveMatchingRule($court, $date, $startTime, $endTime);

        if ($rule !== null) {
            return (int) $rule->price_per_hour;
        }

        return (int) $court->default_hourly_rate;
    }

    /**
     * Resolve matching pricing rule or null if none covers the interval.
     * Uses pricing rule hierarchy: most specific (narrowest time window) first, then highest rate.
     */
    public function resolveMatchingRule(
        Court $court,
        CarbonInterface|string $date,
        string $startTime,
        string $endTime
    ): ?CourtPricingRule {
        $carbonDate = is_string($date) ? Carbon::parse($date) : $date;
        $dayType = DayType::fromCarbon($carbonDate);

        $normalizedStart = TimeHelper::normalize($startTime);
        $normalizedEnd = TimeHelper::normalize($endTime);

        $rules = $court->relationLoaded('pricingRules')
            ? $court->pricingRules
            : $court->pricingRules()->get();

        $matchingRules = $rules->filter(function (CourtPricingRule $rule) use ($dayType, $normalizedStart, $normalizedEnd) {
            if ($rule->day_type !== $dayType) {
                return false;
            }

            $ruleStart = TimeHelper::normalize((string) $rule->start_time);
            $ruleEnd = TimeHelper::normalize((string) $rule->end_time);

            return $ruleStart <= $normalizedStart && $ruleEnd >= $normalizedEnd;
        });

        if ($matchingRules->isEmpty()) {
            return null;
        }

        // Hierarchy resolution: narrowest time window first (specificity), then highest rate
        return $matchingRules->sort(function (CourtPricingRule $a, CourtPricingRule $b) {
            $durationA = strtotime((string) $a->end_time) - strtotime((string) $a->start_time);
            $durationB = strtotime((string) $b->end_time) - strtotime((string) $b->start_time);

            if ($durationA !== $durationB) {
                return $durationA <=> $durationB;
            }

            return $b->price_per_hour <=> $a->price_per_hour;
        })->first();
    }
}
