<?php

declare(strict_types=1);

namespace App\Enums;

enum SlotStatus: string
{
    case Available = 'available';
    case Held = 'held';
    case Booked = 'booked';
    case Blocked = 'blocked';

    /**
     * Determine if slot can be selected or booked by a player.
     */
    public function isBookable(): bool
    {
        return $this === self::Available;
    }

    /**
     * Determine if the slot price can be updated dynamically by pricing rule propagation.
     */
    public function isMutableByPricing(): bool
    {
        return $this === self::Available;
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
