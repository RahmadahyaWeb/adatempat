<?php

declare(strict_types=1);

namespace App\Enums;

enum SportType: string
{
    case Badminton = 'badminton';
    case Padel = 'padel';
    case Futsal = 'futsal';
    case Tennis = 'tennis';
    case MiniSoccer = 'mini_soccer';

    /**
     * Get user-friendly display label for sport type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Badminton => 'Badminton',
            self::Padel => 'Padel',
            self::Futsal => 'Futsal',
            self::Tennis => 'Tennis',
            self::MiniSoccer => 'Mini Soccer',
        };
    }
}
