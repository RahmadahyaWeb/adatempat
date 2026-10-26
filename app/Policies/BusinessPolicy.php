<?php

namespace App\Policies;

use App\Models\Business;
use App\Models\User;

class BusinessPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isOwner();
    }

    public function view(User $user, Business $business): bool
    {
        return $user->isOwner()
            && (int) $business->owner_id === (int) $user->getKey();
    }

    public function create(User $user): bool
    {
        return $user->isOwner();
    }

    public function update(User $user, Business $business): bool
    {
        return $user->isOwner()
            && (int) $business->owner_id === (int) $user->getKey()
            && $business->status !== Business::STATUS_SUSPENDED;
    }

    public function delete(User $user, Business $business): bool
    {
        return false;
    }
}
