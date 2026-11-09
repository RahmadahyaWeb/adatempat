<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Court;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Access\Response;

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
     * Determine whether the user can query or generate slots for a court and date.
     * Enforces tenant ownership and venue booking horizon at the policy boundary.
     */
    public function viewSlots(User $user, Court $court, ?string $date = null): Response
    {
        if (! $user->isVenueOperator() || $user->venue_id !== $court->venue_id) {
            return Response::deny('Unauthorized court access.');
        }

        if ($date !== null) {
            $target = Carbon::parse($date)->startOfDay();
            $today = now()->startOfDay();

            if ($target->lt($today)) {
                return Response::deny('Court slots cannot be queried or generated for past dates.');
            }

            $venue = $court->relationLoaded('venue') ? $court->venue : $court->venue()->first();
            $horizonDays = $venue ? (int) $venue->booking_horizon_days : 30;
            $maxDate = $today->copy()->addDays($horizonDays);

            if ($target->gt($maxDate)) {
                return Response::deny(
                    sprintf('Date %s exceeds venue booking horizon of %d days.', $target->toDateString(), $horizonDays)
                );
            }
        }

        return Response::allow();
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
