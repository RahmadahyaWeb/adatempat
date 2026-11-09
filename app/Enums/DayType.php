<?php

declare(strict_types=1);

namespace App\Enums;

use Carbon\CarbonInterface;

enum DayType: string
{
    case Weekday = 'weekday';
    case Weekend = 'weekend';

    /**
     * Resolve the day type from a Carbon date instance.
     */
    public static function fromCarbon(CarbonInterface $date): self
    {
        return $date->isWeekend() ? self::Weekend : self::Weekday;
    }

    /**
     * Return all available values.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
