<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\Venue;

class VenuePolicy
{
    /**
     * Determine whether the user can view the venue.
     */
    public function view(User $user, Venue $venue): bool
    {
        return $user->isVenueOperator() && $user->venue_id === $venue->id;
    }

    /**
     * Determine whether the user can update the venue profile.
     */
    public function update(User $user, Venue $venue): bool
    {
        return $user->isVenueOperator() && $user->venue_id === $venue->id;
    }
}
