<?php

namespace App\Enums;

enum SpaceCategory: string
{
    case Badminton = 'badminton';
    case Futsal = 'futsal';
    case Basketball = 'basketball';
    case Tennis = 'tennis';
    case Meeting = 'meeting';
    case Studio = 'studio';
    case Coworking = 'coworking';
    case Event = 'event';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Badminton => 'Badminton',
            self::Futsal => 'Futsal',
            self::Basketball => 'Basket',
            self::Tennis => 'Tenis',
            self::Meeting => 'Ruang Meeting',
            self::Studio => 'Studio',
            self::Coworking => 'Coworking',
            self::Event => 'Ruang Acara',
            self::Other => 'Lainnya',
        };
    }
}
