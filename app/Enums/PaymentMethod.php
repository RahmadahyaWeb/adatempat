<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Qris = 'qris';

    public function label(): string
    {
        return match ($this) {
            self::Qris => 'QRIS',
        };
    }
}
