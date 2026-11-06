<?php

namespace App\Enums;

enum AddOnPricingType: string
{
    case PerHour = 'per_hour';
    case PerItem = 'per_item';
    case PerBooking = 'per_booking';

    public function label(): string
    {
        return match ($this) {
            self::PerHour => 'Per jam',
            self::PerItem => 'Per item',
            self::PerBooking => 'Per booking',
        };
    }
}
