<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Court;
use App\Models\User;

class CourtPolicy
{
    /**
     * Determine whether the user can view any courts.
     */
    public function viewAny(User $user): bool
    {
        return $user->isVenueOperator() && $user->venue_id !== null;
    }

    /**
     * Determine whether the user can view the court.
     */
    public function view(User $user, Court $court): bool
    {
        return $user->isVenueOperator() && $user->venue_id === $court->venue_id;
    }

    /**
     * Determine whether the user can create courts.
     */
    public function create(User $user): bool
    {
        return $user->isVenueOperator() && $user->venue_id !== null;
    }

    /**
     * Determine whether the user can update the court.
     */
    public function update(User $user, Court $court): bool
    {
        return $user->isVenueOperator() && $user->venue_id === $court->venue_id;
    }

    /**
     * Determine whether the user can delete the court.
     */
    public function delete(User $user, Court $court): bool
    {
        return $user->isVenueOperator() && $user->venue_id === $court->venue_id;
    }
}
