<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Enums\VenueApprovalStatus;
use App\Models\User;
use App\Models\Venue;

class VenuePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Owner || $user->role === UserRole::Superadmin;
    }

    public function view(User $user, Venue $venue): bool
    {
        if ($user->role === UserRole::Superadmin) {
            return true;
        }

        return $user->role === UserRole::Owner && $venue->business !== null && $venue->business->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        if ($user->role === UserRole::Superadmin) {
            return true;
        }

        return $user->role === UserRole::Owner && $user->businesses()->exists();
    }

    public function update(User $user, Venue $venue): bool
    {
        if ($user->role === UserRole::Superadmin) {
            return true;
        }

        return $user->role === UserRole::Owner && $venue->business !== null && $venue->business->user_id === $user->id;
    }

    public function delete(User $user, Venue $venue): bool
    {
        if ($user->role === UserRole::Superadmin) {
            return true;
        }

        return $user->role === UserRole::Owner && $venue->business !== null && $venue->business->user_id === $user->id;
    }

    public function publish(User $user, Venue $venue): bool
    {
        $canManage = $user->role === UserRole::Superadmin || ($user->role === UserRole::Owner && $venue->business !== null && $venue->business->user_id === $user->id);

        return $canManage && $venue->approval_status === VenueApprovalStatus::Approved;
    }

    public function submitApproval(User $user, Venue $venue): bool
    {
        return $user->role === UserRole::Superadmin || ($user->role === UserRole::Owner && $venue->business !== null && $venue->business->user_id === $user->id);
    }

    public function approve(User $user, Venue $venue): bool
    {
        return $user->role === UserRole::Superadmin;
    }

    public function reject(User $user, Venue $venue): bool
    {
        return $user->role === UserRole::Superadmin;
    }

    public function unpublish(User $user, Venue $venue): bool
    {
        return $user->role === UserRole::Superadmin;
    }
}
