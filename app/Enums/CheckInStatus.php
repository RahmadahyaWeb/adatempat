<?php

declare(strict_types=1);

namespace App\Enums;

enum CheckInStatus: string
{
    case Unclaimed = 'unclaimed';
    case CheckedIn = 'checked_in';

    /**
     * Determine if digital ticket has already been checked in.
     */
    public function isCheckedIn(): bool
    {
        return $this === self::CheckedIn;
    }

    /**
     * Return all available enum string values.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
