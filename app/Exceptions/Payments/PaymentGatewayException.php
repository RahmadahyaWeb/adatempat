<?php

namespace App\Exceptions\Payments;

use RuntimeException;
use Throwable;

class PaymentGatewayException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly bool $definitiveFailure = false,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function isDefinitiveFailure(): bool
    {
        return $this->definitiveFailure;
    }
}
