<?php

namespace App\Enums;

enum CancellationPolicy: string
{
    case FreeCancellation = 'free_cancellation';
    case PartialRefund = 'partial_refund';
    case NonRefundable = 'non_refundable';

    public function label(): string
    {
        return match ($this) {
            self::FreeCancellation => 'Batal gratis',
            self::PartialRefund => 'Refund sebagian',
            self::NonRefundable => 'Tidak dapat direfund',
        };
    }
}
