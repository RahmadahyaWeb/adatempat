<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CheckInStatus;
use App\Enums\PaymentStatus;
use App\Enums\SlotStatus;
use App\Exceptions\ReschedulePolicyException;
use App\Exceptions\SlotUnavailableException;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\CourtSlot;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class RescheduleService
{
    /**
     * Lock duration for temporary Redis lock during reschedule execution.
     */
    public const int REPLACEMENT_LOCK_TTL_SECONDS = 30;

    /**
     * Reschedule a paid reservation to an alternative available court slot.
     *
     * @throws ReschedulePolicyException
     * @throws SlotUnavailableException
     * @throws Throwable
     */
    public function reschedule(
        User $player,
        Booking $booking,
        int $replacementSlotId,
        ?int $originalSlotId = null,
    ): Booking {
        $booking->loadMissing(['venue', 'items.courtSlot.court']);

        // Ownership assertion (defense-in-depth)
        if ($booking->user_id !== $player->id) {
            throw new ReschedulePolicyException('Player does not own this booking.', 403);
        }

        // Gate 1: Venue reschedule policy configuration
        $venue = $booking->venue;
        if (! $venue || ! $venue->allow_reschedule) {
            throw new ReschedulePolicyException('Venue policy does not permit rescheduling.', 422);
        }

        // Gate 2: Single-use limit
        if ($booking->rescheduled_at !== null || $booking->payment_status === PaymentStatus::Rescheduled) {
            throw new ReschedulePolicyException('This booking has already been rescheduled.', 422);
        }

        // Gate 3: Financial status
        if ($booking->payment_status !== PaymentStatus::Paid) {
            throw new ReschedulePolicyException('Only paid bookings can be rescheduled.', 422);
        }

        // Gate 4: Attendance check-in status
        if ($booking->check_in_status !== CheckInStatus::Unclaimed) {
            throw new ReschedulePolicyException('Cannot reschedule a booking that has already been checked in.', 422);
        }

        // Gate 5: H-1 Cutoff (at least 24 hours prior to earliest slot start time)
        $earliestSlotStart = $this->resolveEarliestSlotStart($booking);
        if ($earliestSlotStart !== null) {
            $cutoff = Carbon::now()->addHours(24);
            if ($cutoff->gt($earliestSlotStart)) {
                throw new ReschedulePolicyException(
                    'Reschedule must be initiated at least 24 hours prior to the booked slot start time.',
                    422,
                );
            }
        }

        // Gate 6: Resolve target original booking item
        $bookingItem = $this->resolveTargetBookingItem($booking, $originalSlotId);
        $originalSlot = $bookingItem->courtSlot;

        // Gate 7: Replacement slot validation
        /** @var CourtSlot|null $replacementSlot */
        $replacementSlot = CourtSlot::query()
            ->with(['court.venue'])
            ->find($replacementSlotId);

        if (! $replacementSlot) {
            throw new ReschedulePolicyException('Replacement slot not found.', 404);
        }

        if ($replacementSlot->court->venue_id !== $venue->id) {
            throw new ReschedulePolicyException('Replacement slot must belong to the same venue.', 422);
        }

        $replacementDateStr = is_object($replacementSlot->date) ? $replacementSlot->date->format('Y-m-d') : (string) $replacementSlot->date;
        $replacementStart = Carbon::parse("{$replacementDateStr} {$replacementSlot->start_time}");
        if ($replacementStart->lte(now())) {
            throw new ReschedulePolicyException('Replacement slot must be scheduled in the future.', 422);
        }

        $origSportType = $originalSlot?->court?->sport_type;
        $replacementSportType = $replacementSlot->court?->sport_type;
        if ($origSportType !== null && $replacementSportType !== $origSportType) {
            throw new ReschedulePolicyException(
                'Replacement slot must belong to an equivalent court with the same sport type.',
                422,
            );
        }

        if ($replacementSlot->price !== $bookingItem->price_locked) {
            throw new ReschedulePolicyException(
                'Replacement slot price must match the original booked price exactly.',
                422,
            );
        }

        if (! $replacementSlot->isBookable()) {
            throw new SlotUnavailableException("Slot {$replacementSlotId} is currently held by another player or locked.");
        }

        // Acquire temporary Redis lock on replacement slot
        $lockKey = "court_slot:lock:{$replacementSlotId}";
        $ownerToken = $booking->booking_code.':reschedule';
        $lock = Cache::lock($lockKey, self::REPLACEMENT_LOCK_TTL_SECONDS, $ownerToken);

        if (! $lock->get()) {
            throw new SlotUnavailableException("Slot {$replacementSlotId} is currently held by another player or locked.");
        }

        try {
            // Atomic slot swap inside database transaction with pessimistic row locks
            return DB::transaction(function () use ($booking, $bookingItem, $replacementSlotId): Booking {
                // Re-lock booking row to prevent concurrent reschedule requests
                /** @var Booking $lockedBooking */
                $lockedBooking = Booking::query()
                    ->where('id', $booking->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($lockedBooking->rescheduled_at !== null || $lockedBooking->payment_status === PaymentStatus::Rescheduled) {
                    throw new ReschedulePolicyException('This booking has already been rescheduled.', 422);
                }

                $slotIds = [$bookingItem->court_slot_id, $replacementSlotId];
                sort($slotIds, SORT_NUMERIC);

                $lockedSlots = CourtSlot::query()
                    ->whereIn('id', $slotIds)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                /** @var CourtSlot|null $origSlotLocked */
                $origSlotLocked = $lockedSlots->get($bookingItem->court_slot_id);
                /** @var CourtSlot|null $newSlotLocked */
                $newSlotLocked = $lockedSlots->get($replacementSlotId);

                if (! $origSlotLocked || ! $newSlotLocked) {
                    throw new ReschedulePolicyException('Court slots could not be locked for swap.', 422);
                }

                if ($newSlotLocked->resolveEffectiveStatus() !== SlotStatus::Available) {
                    throw new SlotUnavailableException("Slot {$replacementSlotId} is no longer available.");
                }

                // Swap statuses
                $origSlotLocked->update([
                    'status' => SlotStatus::Available,
                    'locked_until' => null,
                ]);

                $newSlotLocked->update([
                    'status' => SlotStatus::Booked,
                    'locked_until' => null,
                ]);

                // Update booking item
                $bookingItem->update([
                    'court_slot_id' => $newSlotLocked->id,
                ]);

                // Update booking status and timestamp
                $lockedBooking->update([
                    'payment_status' => PaymentStatus::Rescheduled,
                    'rescheduled_at' => now(),
                ]);

                return $lockedBooking->fresh(['items.courtSlot.court', 'venue']);
            });
        } finally {
            // Release temporary Redis lock post-commit or on rollback
            $lock->forceRelease();
        }
    }

    /**
     * Resolve the earliest scheduled start datetime among all booked slots in the booking.
     */
    protected function resolveEarliestSlotStart(Booking $booking): ?Carbon
    {
        $earliest = null;

        foreach ($booking->items as $item) {
            $slot = $item->courtSlot;
            if (! $slot) {
                continue;
            }

            $dateString = is_object($slot->date) ? $slot->date->format('Y-m-d') : (string) $slot->date;
            $startDateTime = Carbon::parse("{$dateString} {$slot->start_time}");

            if ($earliest === null || $startDateTime->lt($earliest)) {
                $earliest = $startDateTime;
            }
        }

        return $earliest;
    }

    /**
     * Resolve which booking item should be swapped.
     *
     * @throws ReschedulePolicyException
     */
    protected function resolveTargetBookingItem(Booking $booking, ?int $originalSlotId): BookingItem
    {
        if ($originalSlotId !== null) {
            $item = $booking->items->firstWhere('court_slot_id', $originalSlotId);
            if (! $item) {
                throw new ReschedulePolicyException('Specified original slot does not belong to this booking.', 422);
            }

            return $item;
        }

        if ($booking->items->count() === 1) {
            /** @var BookingItem $item */
            $item = $booking->items->first();

            return $item;
        }

        throw new ReschedulePolicyException('Original slot ID must be specified for multi-item bookings.', 422);
    }
}
