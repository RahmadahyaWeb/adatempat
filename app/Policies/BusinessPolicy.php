<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Business;
use App\Models\User;

class BusinessPolicy
{
    public function view(User $user, Business $business): bool
    {
        return $user->role === UserRole::Superadmin || $business->user_id === $user->id;
    }

    public function update(User $user, Business $business): bool
    {
        return $user->role === UserRole::Owner && $business->user_id === $user->id;
    }
}
