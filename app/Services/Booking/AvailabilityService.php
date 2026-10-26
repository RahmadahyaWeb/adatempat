<?php

namespace App\Services\Booking;

use App\Models\Booking;
use App\Models\BookingHold;
use App\Models\Business;
use App\Models\Category;
use App\Models\Venue;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class AvailabilityService
{
    /**
     * The requested instants must be normalized to UTC before calling this method.
     * This is a read-only availability check; the booking engine must lock the venue
     * and repeat this check inside its transaction before creating a hold or booking.
     */
    public function isAvailable(
        Venue $venue,
        CarbonImmutable $startsAtUtc,
        CarbonImmutable $endsAtUtc,
        ?int $ignoreHoldId = null,
        ?int $ignoreBookingId = null,
    ): bool {
        $startsAtUtc = $startsAtUtc->utc();
        $endsAtUtc = $endsAtUtc->utc();

        if (! $startsAtUtc->isFuture() || ! $startsAtUtc->isBefore($endsAtUtc)) {
            return false;
        }

        $venue->loadMissing(['business', 'category', 'availabilityRules']);

        if (
            $venue->status !== Venue::STATUS_PUBLISHED
            || $venue->published_at === null
            || $venue->business?->status !== Business::STATUS_ACTIVE
            || $venue->business?->published_at === null
            || $venue->category?->status !== Category::STATUS_ACTIVE
        ) {
            return false;
        }

        if (! $this->fitsOperatingHours($venue, $startsAtUtc, $endsAtUtc)) {
            return false;
        }

        if ($this->overlapsManualBlock($venue, $startsAtUtc, $endsAtUtc)) {
            return false;
        }

        $bufferMinutes = (int) $venue->availabilityRules->max('buffer_minutes');
        $conflictStart = $startsAtUtc->subMinutes($bufferMinutes);
        $conflictEnd = $endsAtUtc->addMinutes($bufferMinutes);

        if ($this->overlapsHold($venue, $conflictStart, $conflictEnd, $ignoreHoldId)) {
            return false;
        }

        return ! $this->overlapsBooking($venue, $conflictStart, $conflictEnd, $ignoreBookingId);
    }

    public function assertAvailable(
        Venue $venue,
        CarbonImmutable $startsAtUtc,
        CarbonImmutable $endsAtUtc,
        ?int $ignoreHoldId = null,
        ?int $ignoreBookingId = null,
    ): void {
        if (! $this->isAvailable($venue, $startsAtUtc, $endsAtUtc, $ignoreHoldId, $ignoreBookingId)) {
            throw ValidationException::withMessages([
                'bookingStart' => 'Jadwal tidak tersedia. Pilih waktu lain atau periksa jam operasional venue.',
            ]);
        }
    }

    private function fitsOperatingHours(
        Venue $venue,
        CarbonImmutable $startsAtUtc,
        CarbonImmutable $endsAtUtc,
    ): bool {
        $timezone = $venue->business?->timezone ?: 'Asia/Jakarta';
        $startsLocal = $startsAtUtc->setTimezone($timezone);
        $endsLocal = $endsAtUtc->setTimezone($timezone);
        $rulesByDay = $venue->availabilityRules->keyBy('day_of_week');

        if ($rulesByDay->isEmpty()) {
            return false;
        }

        $firstDate = $startsLocal->startOfDay()->subDay();
        $lastDate = $endsLocal->startOfDay();

        for ($date = $firstDate; $date->lessThanOrEqualTo($lastDate); $date = $date->addDay()) {
            $rule = $rulesByDay->get($date->dayOfWeek);

            if (! $rule || ! $rule->is_open) {
                continue;
            }

            $openTime = substr((string) $rule->open_time, 0, 8);
            $closeTime = substr((string) $rule->close_time, 0, 8);

            if ($openTime === $closeTime) {
                continue;
            }

            $opensAt = CarbonImmutable::parse($date->format('Y-m-d') . ' ' . $openTime, $timezone);
            $closesAt = CarbonImmutable::parse($date->format('Y-m-d') . ' ' . $closeTime, $timezone);

            if ($closesAt->lessThanOrEqualTo($opensAt)) {
                $closesAt = $closesAt->addDay();
            }

            if (
                $startsLocal->greaterThanOrEqualTo($opensAt)
                && $endsLocal->lessThanOrEqualTo($closesAt)
            ) {
                return true;
            }
        }

        return false;
    }

    private function overlapsManualBlock(
        Venue $venue,
        CarbonImmutable $startsAtUtc,
        CarbonImmutable $endsAtUtc,
    ): bool {
        return $venue->blocks()
            ->where('starts_at', '<', $endsAtUtc)
            ->where('ends_at', '>', $startsAtUtc)
            ->exists();
    }

    private function overlapsHold(
        Venue $venue,
        CarbonImmutable $startsAtUtc,
        CarbonImmutable $endsAtUtc,
        ?int $ignoreHoldId,
    ): bool {
        return BookingHold::query()
            ->where('venue_id', $venue->getKey())
            ->whereIn('status', [BookingHold::STATUS_ACTIVE, BookingHold::STATUS_RECONCILING])
            ->where(function ($query): void {
                $query->where('status', BookingHold::STATUS_RECONCILING)
                    ->orWhere(function ($activeQuery): void {
                        $activeQuery->where('status', BookingHold::STATUS_ACTIVE)
                            ->where('expires_at', '>', now('UTC'));
                    });
            })
            ->when($ignoreHoldId !== null, fn ($query) => $query->where('id', '!=', $ignoreHoldId))
            ->where('starts_at', '<', $endsAtUtc)
            ->where('ends_at', '>', $startsAtUtc)
            ->exists();
    }

    private function overlapsBooking(
        Venue $venue,
        CarbonImmutable $startsAtUtc,
        CarbonImmutable $endsAtUtc,
        ?int $ignoreBookingId = null,
    ): bool {
        return Booking::query()
            ->where('venue_id', $venue->getKey())
            ->where(function ($query): void {
                $query->whereIn('status', Booking::SLOT_BLOCKING_STATUSES)
                    ->orWhere(function ($cancelledQuery): void {
                        $cancelledQuery->where('status', Booking::STATUS_CANCELLED_BY_CUSTOMER)
                            ->whereNull('slot_released_at');
                    });
            })
            ->when($ignoreBookingId !== null, fn ($query) => $query->where('id', '!=', $ignoreBookingId))
            ->where('booking_start', '<', $endsAtUtc)
            ->where('booking_end', '>', $startsAtUtc)
            ->exists();
    }
}
