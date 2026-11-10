<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Expired = 'expired';
    case Rescheduled = 'rescheduled';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

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
     * Determine if the booking reservation has completed its duration.
     */
    public function isCompleted(): bool
    {
        return $this === self::Completed;
    }

    /**
     * Determine if the booking has been cancelled.
     */
    public function isCancelled(): bool
    {
        return $this === self::Cancelled;
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
