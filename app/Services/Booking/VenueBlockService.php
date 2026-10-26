<?php

namespace App\Services\Booking;

use App\Models\User;
use App\Models\Venue;
use App\Models\VenueBlock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VenueBlockService
{
    public function create(
        User $owner,
        Venue $venue,
        CarbonImmutable $startsAtUtc,
        CarbonImmutable $endsAtUtc,
        string $reason,
    ): VenueBlock {
        if (! $startsAtUtc->isBefore($endsAtUtc)) {
            throw ValidationException::withMessages([
                'blockEndsAt' => 'Waktu selesai harus lebih akhir daripada waktu mulai.',
            ]);
        }

        return DB::transaction(function () use ($owner, $venue, $startsAtUtc, $endsAtUtc, $reason): VenueBlock {
            $lockedVenue = Venue::query()->whereKey($venue->getKey())->lockForUpdate()->firstOrFail();

            return $lockedVenue->blocks()->create([
                'starts_at' => $startsAtUtc->utc(),
                'ends_at' => $endsAtUtc->utc(),
                'reason' => trim($reason),
                'created_by' => $owner->getKey(),
            ]);
        });
    }

    public function delete(User $owner, Venue $venue, int $blockId): void
    {
        DB::transaction(function () use ($venue, $blockId): void {
            $lockedVenue = Venue::query()->whereKey($venue->getKey())->lockForUpdate()->firstOrFail();
            $block = $lockedVenue->blocks()->whereKey($blockId)->firstOrFail();
            $block->delete();
        });
    }
}
