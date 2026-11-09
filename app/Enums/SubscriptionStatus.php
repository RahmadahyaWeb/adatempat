<?php

declare(strict_types=1);

namespace App\Enums;

enum SubscriptionStatus: string
{
    case Trial = 'trial';
    case Active = 'active';
    case Suspended = 'suspended';
    case Expired = 'expired';

    /**
     * Determine whether backoffice mutations are permitted under this subscription status.
     */
    public function canMutate(): bool
    {
        return match ($this) {
            self::Trial, self::Active => true,
            self::Suspended, self::Expired => false,
        };
    }
}
