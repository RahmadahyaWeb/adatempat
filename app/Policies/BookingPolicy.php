<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;

class BookingPolicy
{
    /**
     * Determine whether the user can view the booking / digital ticket.
     */
    public function view(User $user, Booking $booking): bool
    {
        if ($booking->user_id === $user->id) {
            return true;
        }

        if ($user->isVenueOperator() && $user->venue_id === $booking->venue_id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can verify front-desk check-in for the booking.
     */
    public function checkIn(User $user, Booking $booking): bool
    {
        return $user->isVenueOperator() && $user->venue_id === $booking->venue_id;
    }

    /**
     * Determine whether the user can reschedule the booking.
     */
    public function reschedule(User $user, Booking $booking): bool
    {
        return $booking->user_id === $user->id;
    }
}
