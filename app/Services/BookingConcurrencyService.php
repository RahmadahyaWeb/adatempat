<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CheckInStatus;
use App\Enums\PaymentStatus;
use App\Enums\SlotStatus;
use App\Exceptions\SlotUnavailableException;
use App\Models\Booking;
use App\Models\CourtSlot;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class BookingConcurrencyService
{
    /**
     * Lock duration for checkout hold (10 minutes = 600 seconds).
     */
    public const int HOLD_TTL_SECONDS = 600;

    /**
     * Acquire multi-slot checkout hold locks, update database slots, and generate a pending draft booking.
     *
     * @param  list<int>  $slotIds
     *
     * @throws SlotUnavailableException
     * @throws Throwable
     */
    public function holdSlots(User $player, array $slotIds, ?int $courtId = null, ?string $date = null): Booking
    {
        // 1. Sort slot IDs numerically in ascending order to prevent deadlocks across concurrent batches
        $sortedSlotIds = array_values(array_unique(array_map('intval', $slotIds)));
        if (empty($sortedSlotIds)) {
            throw new SlotUnavailableException('No court slots specified for checkout hold.');
        }

        sort($sortedSlotIds, SORT_NUMERIC);

        $now = now();
        $bookingCode = Booking::generateBookingCode($now);
        $ownerToken = $bookingCode;

        /** @var list<Lock> $acquiredLocks */
        $acquiredLocks = [];

        try {
            // 2. Phase 1: Acquire atomic Redis locks per slot with 600s TTL and booking reference owner token
            foreach ($sortedSlotIds as $slotId) {
                $lockKey = "court_slot:lock:{$slotId}";
                $lock = Cache::lock($lockKey, self::HOLD_TTL_SECONDS, $ownerToken);

                if (! $lock->get()) {
                    throw new SlotUnavailableException("Slot {$slotId} is currently held by another player or locked.");
                }

                $acquiredLocks[] = $lock;
            }

            // 3. Phase 2: Synchronize hold status into MySQL database inside atomic transaction
            return DB::transaction(function () use ($player, $sortedSlotIds, $now, $bookingCode, $courtId, $date): Booking {
                $holdUntil = $now->copy()->addSeconds(self::HOLD_TTL_SECONDS);

                // Acquire pessimistic row-level locks
                $slots = CourtSlot::query()
                    ->whereIn('id', $sortedSlotIds)
                    ->with(['court.venue'])
                    ->lockForUpdate()
                    ->get();

                if ($slots->count() !== count($sortedSlotIds)) {
                    throw new SlotUnavailableException('One or more selected slots could not be found.');
                }

                // Verify optional court and date filters match all target slots
                if ($courtId !== null && $slots->some(fn (CourtSlot $s) => $s->court_id !== $courtId)) {
                    throw new SlotUnavailableException('One or more selected slots do not match the specified court.');
                }

                if ($date !== null && $slots->some(fn (CourtSlot $s) => (is_object($s->date) ? $s->date->format('Y-m-d') : (string) $s->date) !== $date)) {
                    throw new SlotUnavailableException('One or more selected slots do not match the specified date.');
                }

                // Verify each slot's bookability and operational constraints
                foreach ($slots as $slot) {
                    $effectiveStatus = $slot->resolveEffectiveStatus($now);
                    if ($effectiveStatus !== SlotStatus::Available) {
                        throw new SlotUnavailableException("Slot {$slot->id} is no longer available.");
                    }

                    $court = $slot->court;
                    if (! $court || ! $court->is_active) {
                        throw new SlotUnavailableException("Court for slot {$slot->id} is currently inactive.");
                    }

                    $venue = $court->venue;
                    if (! $venue || ! $venue->hasActiveSubscription()) {
                        throw new SlotUnavailableException("Venue for slot {$slot->id} is currently unavailable.");
                    }
                }

                // Verify all slots belong to the same venue
                $venueIds = $slots->pluck('court.venue_id')->unique();
                if ($venueIds->count() > 1) {
                    throw new SlotUnavailableException('All selected court slots must belong to the same venue.');
                }

                $venue = $slots->first()->court->venue;
                $totalAmount = (int) $slots->sum('price');

                // Update slot statuses and hold expiration in database
                foreach ($slots as $slot) {
                    $slot->update([
                        'status' => SlotStatus::Held,
                        'locked_until' => $holdUntil,
                    ]);
                }

                // Create pending draft booking
                $booking = Booking::create([
                    'booking_code' => $bookingCode,
                    'user_id' => $player->id,
                    'venue_id' => $venue->id,
                    'total_amount' => $totalAmount,
                    'payment_status' => PaymentStatus::Pending,
                    'check_in_status' => CheckInStatus::Unclaimed,
                    'qr_token' => Booking::generateQrToken(),
                    'qris_expires_at' => $holdUntil,
                ]);

                // Create locked-price booking items
                foreach ($slots as $slot) {
                    $booking->items()->create([
                        'court_slot_id' => $slot->id,
                        'price_locked' => $slot->price,
                    ]);
                }

                return $booking->load(['items.courtSlot.court', 'venue']);
            });
        } catch (Throwable $e) {
            // Fail-fast rollback: release all partially or fully acquired Redis locks immediately
            foreach ($acquiredLocks as $lock) {
                $lock->forceRelease();
            }

            throw $e;
        }
    }

    /**
     * Explicitly abandon checkout, resetting slot statuses to available and releasing Redis locks.
     */
    public function abandonHold(Booking $booking): void
    {
        if ($booking->payment_status !== PaymentStatus::Pending) {
            return;
        }

        $now = now();
        $isExpired = $booking->qris_expires_at !== null && $booking->qris_expires_at->lte($now);

        if ($isExpired) {
            $booking->update([
                'payment_status' => PaymentStatus::Expired,
            ]);

            return;
        }

        $slotIds = DB::transaction(function () use ($booking): array {
            $booking->update([
                'payment_status' => PaymentStatus::Expired,
            ]);

            return $booking->items()->pluck('court_slot_id')->all();
        });

        $this->resetSlotsToAvailable($slotIds, $booking->qris_expires_at);
    }

    /**
     * Scheduled sweep cleaning expired draft bookings and resetting database slot statuses.
     *
     * @return int Number of pruned bookings
     */
    public function pruneExpiredHolds(): int
    {
        $now = now();

        return DB::transaction(function () use ($now): int {
            // Find pending bookings where hold window has elapsed
            $expiredBookings = Booking::query()
                ->where('payment_status', PaymentStatus::Pending)
                ->where(function ($query) use ($now) {
                    $query->where('created_at', '<=', $now->copy()->subSeconds(self::HOLD_TTL_SECONDS))
                        ->orWhereHas('items.courtSlot', function ($slotQuery) use ($now) {
                            $slotQuery->where('status', SlotStatus::Held)
                                ->where('locked_until', '<=', $now);
                        });
                })
                ->with('items')
                ->get();

            $prunedCount = 0;
            $allSlotIdsToRelease = [];

            foreach ($expiredBookings as $booking) {
                $booking->update([
                    'payment_status' => PaymentStatus::Expired,
                ]);

                $slotIds = $booking->items->pluck('court_slot_id')->all();
                $allSlotIdsToRelease = array_merge($allSlotIdsToRelease, $slotIds);
                $prunedCount++;
            }

            // Find any lingering held slots with expired locked_until
            $lingeringSlots = CourtSlot::query()
                ->where('status', SlotStatus::Held)
                ->where('locked_until', '<=', $now)
                ->pluck('id')
                ->all();

            $allSlotIdsToRelease = array_values(array_unique(array_merge($allSlotIdsToRelease, $lingeringSlots)));

            if (! empty($allSlotIdsToRelease)) {
                $this->resetSlotsToAvailable($allSlotIdsToRelease, $now);
            }

            return $prunedCount;
        });
    }

    /**
     * Reset court slots to available status in DB and force-release Redis locks.
     *
     * @param  list<int>  $slotIds
     */
    protected function resetSlotsToAvailable(array $slotIds, ?CarbonInterface $maxLockedUntil = null): void
    {
        if (empty($slotIds)) {
            return;
        }

        $query = CourtSlot::query()
            ->whereIn('id', $slotIds)
            ->where('status', SlotStatus::Held);

        if ($maxLockedUntil !== null) {
            $query->where('locked_until', '<=', $maxLockedUntil);
        }

        $releasedSlotIds = (clone $query)->pluck('id')->all();

        $query->update([
            'status' => SlotStatus::Available,
            'locked_until' => null,
        ]);

        $this->releaseRedisLocks($releasedSlotIds);
    }

    /**
     * Force release Redis atomic locks for the given slot IDs.
     *
     * @param  list<int>  $slotIds
     */
    public function releaseRedisLocks(array $slotIds): void
    {
        foreach ($slotIds as $slotId) {
            Cache::lock("court_slot:lock:{$slotId}")->forceRelease();
        }
    }
}
