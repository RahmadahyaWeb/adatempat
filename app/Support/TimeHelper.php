<?php

declare(strict_types=1);

namespace App\Support;

class TimeHelper
{
    /**
     * Normalize a time string into standard HH:MM:SS format.
     */
    public static function normalize(string $time): string
    {
        $parts = explode(':', trim($time));
        $hours = str_pad($parts[0] ?? '00', 2, '0', STR_PAD_LEFT);
        $minutes = str_pad($parts[1] ?? '00', 2, '0', STR_PAD_LEFT);
        $seconds = str_pad($parts[2] ?? '00', 2, '0', STR_PAD_LEFT);

        return sprintf('%s:%s:%s', $hours, $minutes, $seconds);
    }
}
