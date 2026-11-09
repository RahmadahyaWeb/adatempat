<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class BookingHorizonExceededException extends Exception
{
    public function __construct(string $message = 'Selected date exceeds the venue booking horizon or is in the past.')
    {
        parent::__construct($message);
    }
}
