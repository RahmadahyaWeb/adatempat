<?php

namespace App\Policies;

use App\Models\Business;
use App\Models\User;
use App\Models\Venue;

class VenuePolicy
{
    public function viewAny(User $user, Business $business): bool
    {
        return $user->isOwner()
            && (int) $business->owner_id === (int) $user->getKey()
            && $business->status !== Business::STATUS_SUSPENDED;
    }

    public function view(User $user, Venue $venue): bool
    {
        return $user->isOwner()
            && (int) $venue->business?->owner_id === (int) $user->getKey();
    }

    public function create(User $user, Business $business): bool
    {
        return $user->isOwner()
            && (int) $business->owner_id === (int) $user->getKey()
            && $business->status !== Business::STATUS_SUSPENDED;
    }

    public function update(User $user, Venue $venue): bool
    {
        $venue->loadMissing('business');

        return $user->isOwner()
            && (int) $venue->business?->owner_id === (int) $user->getKey()
            && $venue->business?->status !== Business::STATUS_SUSPENDED
            && $venue->status !== Venue::STATUS_ARCHIVED;
    }

    public function delete(User $user, Venue $venue): bool
    {
        return false;
    }
}
