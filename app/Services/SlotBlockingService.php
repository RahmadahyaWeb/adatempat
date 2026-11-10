<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SlotStatus;
use App\Exceptions\SlotUnavailableException;
use App\Models\CourtSlot;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SlotBlockingService
{
    /**
     * Transition an available court slot to blocked status with an optional explanatory reason.
     *
     * @throws SlotUnavailableException
     * @throws HttpException
     */
    public function blockSlot(User $operator, CourtSlot $slot, ?string $reason = null): CourtSlot
    {
        $this->assertOperatorCanManageSlot($operator, $slot);

        return DB::transaction(function () use ($slot, $reason): CourtSlot {
            /** @var CourtSlot $lockedSlot */
            $lockedSlot = CourtSlot::query()
                ->where('id', $slot->id)
                ->lockForUpdate()
                ->firstOrFail();

            $effectiveStatus = $lockedSlot->resolveEffectiveStatus();

            if ($effectiveStatus === SlotStatus::Held) {
                throw new SlotUnavailableException("Slot {$lockedSlot->id} is currently held in checkout and cannot be blocked.");
            }

            if ($effectiveStatus === SlotStatus::Booked) {
                throw new SlotUnavailableException("Slot {$lockedSlot->id} is already booked and cannot be blocked.");
            }

            if ($effectiveStatus === SlotStatus::Blocked) {
                throw new SlotUnavailableException("Slot {$lockedSlot->id} is already blocked.");
            }

            if ($effectiveStatus !== SlotStatus::Available) {
                throw new SlotUnavailableException("Slot {$lockedSlot->id} is not available for blocking.");
            }

            $lockedSlot->update([
                'status' => SlotStatus::Blocked,
                'block_reason' => $reason,
                'locked_until' => null,
            ]);

            return $lockedSlot->fresh();
        });
    }

    /**
     * Transition a previously blocked court slot back to available status and clear block reason.
     *
     * @throws HttpException
     */
    public function unblockSlot(User $operator, CourtSlot $slot): CourtSlot
    {
        $this->assertOperatorCanManageSlot($operator, $slot);

        return DB::transaction(function () use ($slot): CourtSlot {
            /** @var CourtSlot $lockedSlot */
            $lockedSlot = CourtSlot::query()
                ->where('id', $slot->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedSlot->status !== SlotStatus::Blocked) {
                throw new HttpException(422, "Slot {$lockedSlot->id} is not currently in blocked status.");
            }

            $lockedSlot->update([
                'status' => SlotStatus::Available,
                'block_reason' => null,
                'locked_until' => null,
            ]);

            return $lockedSlot->fresh();
        });
    }

    /**
     * Assert that the operator is authorized to manage the target court slot.
     *
     * @throws HttpException
     */
    protected function assertOperatorCanManageSlot(User $operator, CourtSlot $slot): void
    {
        $court = $slot->relationLoaded('court') ? $slot->court : $slot->court()->firstOrFail();

        if (! $operator->isVenueOperator() || $operator->venue_id !== $court->venue_id) {
            throw new HttpException(403, 'Unauthorized slot operation for another venue.');
        }
    }
}
