<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Space;
use App\Models\User;
use App\Models\Venue;

class SpacePolicy
{
    public function viewAny(User $user, Venue $venue): bool
    {
        if ($user->role === UserRole::Superadmin) {
            return true;
        }

        return $user->role === UserRole::Owner
            && $venue->business !== null
            && $venue->business->user_id === $user->id;
    }

    public function view(User $user, Space $space): bool
    {
        if ($user->role === UserRole::Superadmin) {
            return true;
        }

        $space->loadMissing('venue.business');

        return $user->role === UserRole::Owner
            && $space->venue !== null
            && $space->venue->business !== null
            && $space->venue->business->user_id === $user->id;
    }

    public function create(User $user, Venue $venue): bool
    {
        if ($user->role === UserRole::Superadmin) {
            return true;
        }

        return $user->role === UserRole::Owner
            && $venue->business !== null
            && $venue->business->user_id === $user->id;
    }

    public function update(User $user, Space $space): bool
    {
        if ($user->role === UserRole::Superadmin) {
            return true;
        }

        $space->loadMissing('venue.business');

        return $user->role === UserRole::Owner
            && $space->venue !== null
            && $space->venue->business !== null
            && $space->venue->business->user_id === $user->id;
    }

    public function delete(User $user, Space $space): bool
    {
        if ($user->role === UserRole::Superadmin) {
            return true;
        }

        $space->loadMissing('venue.business');

        return $user->role === UserRole::Owner
            && $space->venue !== null
            && $space->venue->business !== null
            && $space->venue->business->user_id === $user->id;
    }

    public function manageSchedules(User $user, Space $space): bool
    {
        return $this->update($user, $space);
    }

    public function manageAddOns(User $user, Space $space): bool
    {
        return $this->update($user, $space);
    }
}
