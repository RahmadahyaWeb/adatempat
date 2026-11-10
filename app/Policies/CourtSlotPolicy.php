<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CourtSlot;
use App\Models\User;

class CourtSlotPolicy
{
    /**
     * Determine whether the operator can block the court slot.
     */
    public function block(User $user, CourtSlot $slot): bool
    {
        return $this->canManageSlot($user, $slot);
    }

    /**
     * Determine whether the operator can unblock the court slot.
     */
    public function unblock(User $user, CourtSlot $slot): bool
    {
        return $this->canManageSlot($user, $slot);
    }

    /**
     * Check if user is an authorized venue operator for this slot's court venue.
     */
    protected function canManageSlot(User $user, CourtSlot $slot): bool
    {
        $court = $slot->relationLoaded('court') ? $slot->court : $slot->court()->first();

        return $user->isVenueOperator() && $user->venue_id !== null && $user->venue_id === $court?->venue_id;
    }
}
