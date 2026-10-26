<?php

namespace App\Services\Booking;

use App\Models\Booking;
use App\Models\BookingHold;
use App\Models\Venue;
use App\Models\VenuePrice;
use App\Models\VenuePromotion;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class PricingAndPromoService
{
    /**
     * Calculate a deterministic quote. This method does not reserve or consume promotion quota.
     * The booking transaction must re-lock eligible promotions and re-run the calculation
     * before creating a booking/hold.
     *
     * @param array<int, int|string> $sessionPriceIds Required only for session pricing.
     * @return array{
     *   pricing_unit:string,
     *   duration_units:int,
     *   base_amount:int,
     *   owner_promotion_id:?int,
     *   owner_discount_amount:int,
     *   platform_promotion_id:?int,
     *   platform_subsidy_amount:int,
     *   actual_paid_amount:int,
     *   owner_entitlement_before_gateway_fee:int,
     *   promotion_quota_to_consume:array<int, int>
     * }
     */
    public function quote(
        Venue $venue,
        CarbonImmutable $startsAtUtc,
        CarbonImmutable $endsAtUtc,
        array $sessionPriceIds = [],
        bool $lockPromotions = false,
    ): array {
        $startsAtUtc = $startsAtUtc->utc();
        $endsAtUtc = $endsAtUtc->utc();

        if (! $startsAtUtc->isFuture() || ! $startsAtUtc->isBefore($endsAtUtc)) {
            throw ValidationException::withMessages([
                'bookingStart' => 'Waktu booking harus berada di masa depan dan waktu selesai harus setelah waktu mulai.',
            ]);
        }

        $venue->loadMissing(['business', 'prices']);
        $timezone = $venue->business?->timezone ?: 'Asia/Jakarta';
        $startsLocal = $startsAtUtc->setTimezone($timezone);
        $endsLocal = $endsAtUtc->setTimezone($timezone);

        $activePrices = $venue->prices->where('is_active', true)->values();
        $units = $activePrices->pluck('pricing_unit')->unique()->values();

        if ($activePrices->isEmpty() || $units->count() !== 1) {
            throw ValidationException::withMessages([
                'pricing' => 'Venue harus memiliki tepat satu model harga aktif sebelum dapat dipesan.',
            ]);
        }

        $pricingUnit = (string) $units->first();

        [$baseAmount, $durationUnits] = match ($pricingUnit) {
            VenuePrice::UNIT_HOURLY => $this->calculateHourly($activePrices, $startsLocal, $endsLocal),
            VenuePrice::UNIT_DAILY => $this->calculateDaily($activePrices, $startsLocal, $endsLocal),
            VenuePrice::UNIT_SESSION => $this->calculateSessions($activePrices, $sessionPriceIds, $startsLocal, $endsLocal),
            default => throw ValidationException::withMessages([
                'pricing' => 'Model harga venue tidak didukung.',
            ]),
        };

        if ($baseAmount < 0 || $durationUnits < 1) {
            throw ValidationException::withMessages([
                'pricing' => 'Konfigurasi harga atau durasi booking tidak valid.',
            ]);
        }

        $atUtc = CarbonImmutable::now('UTC');
        $eligiblePromotions = VenuePromotion::query()
            ->where('status', VenuePromotion::STATUS_ACTIVE)
            ->where('starts_at', '<=', $atUtc)
            ->where('ends_at', '>', $atUtc)
            ->where(function ($query) use ($venue): void {
                $query->where(function ($ownerQuery) use ($venue): void {
                    $ownerQuery->where('funded_by', VenuePromotion::FUNDED_BY_OWNER)
                        ->where('venue_id', $venue->getKey());
                })->orWhere(function ($platformQuery) use ($venue): void {
                    $platformQuery->where('funded_by', VenuePromotion::FUNDED_BY_PLATFORM)
                        ->where(function ($scopeQuery) use ($venue): void {
                            $scopeQuery->whereNull('venue_id')
                                ->orWhere('venue_id', $venue->getKey());
                        })
                        ->where(function ($businessQuery) use ($venue): void {
                            $businessQuery->whereNull('business_id')
                                ->orWhere('business_id', $venue->business_id);
                        });
                });
            })
            ->where(function ($query): void {
                $query->whereNull('max_quota')
                    ->orWhereColumn('used_count', '<', 'max_quota');
            })
            ->orderBy('id')
            ->when($lockPromotions, fn ($query) => $query->lockForUpdate())
            ->get()
            ->filter(fn (VenuePromotion $promotion): bool => $this->hasAvailableQuota($promotion))
            ->values();

        $ownerPromotions = $eligiblePromotions
            ->where('funded_by', VenuePromotion::FUNDED_BY_OWNER)
            ->map(fn (VenuePromotion $promotion): array => [
                'promotion' => $promotion,
                'amount' => $this->discountAmount($promotion, $baseAmount),
            ])
            ->filter(fn (array $candidate): bool => $candidate['amount'] > 0)
            ->sort(function (array $a, array $b): int {
                return ($b['amount'] <=> $a['amount'])
                    ?: ($a['promotion']->getKey() <=> $b['promotion']->getKey());
            })
            ->values();

        $ownerCandidate = $ownerPromotions->first();
        $ownerPromotion = $ownerCandidate['promotion'] ?? null;
        $ownerDiscount = min($baseAmount, (int) ($ownerCandidate['amount'] ?? 0));
        $afterOwnerDiscount = max(0, $baseAmount - $ownerDiscount);

        $platformPromotions = $eligiblePromotions
            ->where('funded_by', VenuePromotion::FUNDED_BY_PLATFORM)
            ->filter(function (VenuePromotion $promotion) use ($ownerPromotion): bool {
                return $ownerPromotion === null || $promotion->is_combinable_with_owner_promo;
            })
            ->map(fn (VenuePromotion $promotion): array => [
                'promotion' => $promotion,
                'amount' => $this->discountAmount($promotion, $afterOwnerDiscount),
            ])
            ->filter(fn (array $candidate): bool => $candidate['amount'] > 0)
            ->sort(function (array $a, array $b): int {
                return ($b['amount'] <=> $a['amount'])
                    ?: ($a['promotion']->getKey() <=> $b['promotion']->getKey());
            })
            ->values();

        $platformCandidate = $platformPromotions->first();
        $platformPromotion = $platformCandidate['promotion'] ?? null;
        $platformSubsidy = min($afterOwnerDiscount, (int) ($platformCandidate['amount'] ?? 0));
        $actualPaid = max(0, $afterOwnerDiscount - $platformSubsidy);

        $promotionsToConsume = collect([$ownerPromotion, $platformPromotion])
            ->filter()
            ->map(fn (VenuePromotion $promotion): int => $promotion->getKey())
            ->values()
            ->all();

        return [
            'pricing_unit' => $pricingUnit,
            'duration_units' => $durationUnits,
            'base_amount' => $baseAmount,
            'owner_promotion_id' => $ownerPromotion?->getKey(),
            'owner_discount_amount' => $ownerDiscount,
            'platform_promotion_id' => $platformPromotion?->getKey(),
            'platform_subsidy_amount' => $platformSubsidy,
            'actual_paid_amount' => $actualPaid,
            'owner_entitlement_before_gateway_fee' => max(0, $baseAmount - $ownerDiscount),
            'promotion_quota_to_consume' => $promotionsToConsume,
        ];
    }

    private function calculateHourly(
        Collection $prices,
        CarbonImmutable $startsLocal,
        CarbonImmutable $endsLocal,
    ): array {
        if ($startsLocal->greaterThanOrEqualTo($endsLocal)) {
            $this->invalidDuration();
        }

        $price = $prices->first();
        $interval = max(1, (int) $price->step_interval_minutes);
        $durationMinutes = (int) $startsLocal->diffInMinutes($endsLocal);

        if ($durationMinutes % $interval !== 0) {
            throw ValidationException::withMessages([
                'bookingEnd' => 'Durasi booking harus mengikuti interval harga venue.',
            ]);
        }

        $units = intdiv($durationMinutes, $interval);
        $this->validateUnits($price, $units);

        $amount = 0;
        $cursor = $startsLocal;

        while ($cursor->lessThan($endsLocal)) {
            $next = $cursor->addMinutes($interval);
            $amount += $this->unitPriceForDate($price, $cursor);
            $cursor = $next;
        }

        return [$amount, $units];
    }

    private function calculateDaily(
        Collection $prices,
        CarbonImmutable $startsLocal,
        CarbonImmutable $endsLocal,
    ): array {
        $price = $prices->first();

        if (
            $startsLocal->greaterThanOrEqualTo($endsLocal)
            || ! $startsLocal->isStartOfDay()
            || ! $endsLocal->isStartOfDay()
        ) {
            throw ValidationException::withMessages([
                'bookingStart' => 'Booking harian harus menggunakan tanggal check-in dan check-out pada pukul 00:00 zona waktu venue.',
            ]);
        }

        $units = (int) $startsLocal->diffInDays($endsLocal);
        $this->validateUnits($price, $units);

        $amount = 0;
        for ($day = 0; $day < $units; $day++) {
            $amount += $this->unitPriceForDate($price, $startsLocal->addDays($day));
        }

        return [$amount, $units];
    }

    private function calculateSessions(
        Collection $prices,
        array $sessionPriceIds,
        CarbonImmutable $startsLocal,
        CarbonImmutable $endsLocal,
    ): array {
        if ($sessionPriceIds === []) {
            throw ValidationException::withMessages([
                'sessionPriceIds' => 'Pilih minimal satu sesi venue.',
            ]);
        }

        $ids = collect($sessionPriceIds)->map(fn ($id): int => (int) $id)->unique()->values();

        if ($ids->count() !== count($sessionPriceIds)) {
            throw ValidationException::withMessages([
                'sessionPriceIds' => 'Sesi yang sama tidak boleh dipilih lebih dari sekali.',
            ]);
        }

        $selected = $prices->whereIn('id', $ids)->values();

        if ($selected->count() !== $ids->count()) {
            throw ValidationException::withMessages([
                'sessionPriceIds' => 'Salah satu sesi tidak aktif atau bukan milik venue ini.',
            ]);
        }

        $selected = $selected->sortBy('session_start_time')->values();
        $first = $selected->first();
        $last = $selected->last();

        if (! $first->session_start_time || ! $last->session_end_time) {
            throw ValidationException::withMessages([
                'sessionPriceIds' => 'Konfigurasi jam sesi belum lengkap.',
            ]);
        }

        $expectedStart = substr((string) $first->session_start_time, 0, 5);
        $expectedEnd = substr((string) $last->session_end_time, 0, 5);

        if (
            $startsLocal->format('H:i') !== $expectedStart
            || $endsLocal->format('H:i') !== $expectedEnd
            || $startsLocal->toDateString() !== $endsLocal->toDateString()
        ) {
            throw ValidationException::withMessages([
                'sessionPriceIds' => 'Waktu booking harus sama dengan rentang sesi yang dipilih pada satu tanggal.',
            ]);
        }

        for ($index = 1; $index < $selected->count(); $index++) {
            $previousEnd = substr((string) $selected[$index - 1]->session_end_time, 0, 5);
            $currentStart = substr((string) $selected[$index]->session_start_time, 0, 5);

            if ($previousEnd !== $currentStart) {
                throw ValidationException::withMessages([
                    'sessionPriceIds' => 'Sesi yang dipilih harus berurutan tanpa jeda.',
                ]);
            }
        }

        $amount = $selected->sum(fn (VenuePrice $session): int => $this->unitPriceForDate($session, $startsLocal));

        return [$amount, $selected->count()];
    }

    private function unitPriceForDate(VenuePrice $price, CarbonImmutable $date): int
    {
        $isWeekend = $date->isSaturday() || $date->isSunday();

        return $isWeekend && $price->weekend_price_amount !== null
            ? (int) $price->weekend_price_amount
            : (int) $price->price_amount;
    }

    private function hasAvailableQuota(VenuePromotion $promotion): bool
    {
        if ($promotion->max_quota === null) {
            return true;
        }

        $reservedCount = Booking::query()
            ->where(function ($query) use ($promotion): void {
                $query->where('owner_promotion_id', $promotion->getKey())
                    ->orWhere('platform_promotion_id', $promotion->getKey());
            })
            ->whereIn('status', [
                Booking::STATUS_PENDING_PAYMENT,
                Booking::STATUS_PAYMENT_VERIFYING,
                Booking::STATUS_RECONCILIATION_REQUIRED,
            ])
            ->whereHas('holds', function ($query): void {
                $query->whereIn('status', [BookingHold::STATUS_ACTIVE, BookingHold::STATUS_RECONCILING])
                    ->where(function ($holdQuery): void {
                        $holdQuery->where('status', BookingHold::STATUS_RECONCILING)
                            ->orWhere(function ($activeQuery): void {
                                $activeQuery->where('status', BookingHold::STATUS_ACTIVE)
                                    ->where('expires_at', '>', now('UTC'));
                            });
                    });
            })
            ->count();

        return $promotion->used_count + $reservedCount < $promotion->max_quota;
    }

    private function discountAmount(VenuePromotion $promotion, int $amount): int
    {
        if ($amount <= 0) {
            return 0;
        }

        if ($promotion->discount_type === VenuePromotion::DISCOUNT_PERCENTAGE) {
            $percentage = max(0, min(100, (int) $promotion->discount_value));
            $discount = intdiv($amount, 100) * $percentage
                + intdiv(($amount % 100) * $percentage, 100);

            if ($promotion->max_discount_amount !== null) {
                $discount = min($discount, (int) $promotion->max_discount_amount);
            }

            return min($amount, $discount);
        }

        if ($promotion->discount_type === VenuePromotion::DISCOUNT_FIXED) {
            return min($amount, max(0, (int) $promotion->discount_value));
        }

        return 0;
    }

    private function validateUnits(VenuePrice $price, int $units): void
    {
        if (
            $units < (int) $price->min_duration_units
            || $units > (int) $price->max_duration_units
        ) {
            throw ValidationException::withMessages([
                'duration' => 'Durasi booking berada di luar batas minimum atau maksimum venue.',
            ]);
        }
    }

    private function invalidDuration(): never
    {
        throw ValidationException::withMessages([
            'bookingEnd' => 'Waktu selesai harus setelah waktu mulai.',
        ]);
    }
}
