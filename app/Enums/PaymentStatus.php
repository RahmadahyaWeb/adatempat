<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Expired = 'expired';
    case Rescheduled = 'rescheduled';

    /**
     * Determine if the booking is currently pending payment.
     */
    public function isPending(): bool
    {
        return $this === self::Pending;
    }

    /**
     * Determine if the booking has been confirmed and paid.
     */
    public function isPaid(): bool
    {
        return $this === self::Paid;
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
