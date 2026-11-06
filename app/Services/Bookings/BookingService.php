<?php

namespace App\Services\Bookings;

use App\Enums\AddOnPricingType;
use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\SlotConflictException;
use App\Jobs\Bookings\SendBookingConfirmedNotificationJob;
use App\Models\Booking;
use App\Models\BookingAddOn;
use App\Models\BookingSlot;
use App\Models\Space;
use App\Models\SpaceAddOn;
use App\Models\User;
use App\Models\Venue;
use App\Services\Slots\SlotGeneratorService;
use App\Services\Slots\SlotReservationService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class BookingService
{
    public function __construct(
        protected SlotGeneratorService $slotGenerator,
        protected SlotReservationService $slotReservation,
    ) {}

    /**
     * Calculate pricing breakdown for chosen slots and add-ons.
     *
     * @param  array<int, string>  $slotTimes
     * @param  array<int, array{id: int, quantity: int}>  $addOnSelections
     * @param  array<int, array{start_time: string, end_time: string, price: int, status: string}>|null  $availableSlots
     * @return array{
     *     duration_minutes: int,
     *     duration_hours: float,
     *     slot_subtotal: float,
     *     addons_subtotal: float,
     *     total_amount: float,
     *     slots: array<int, array{start_time: string, end_time: string, price: int}>,
     *     addons: array<int, array{
     *         space_add_on_id: int,
     *         name: string,
     *         pricing_type: string,
     *         unit_price: float,
     *         quantity: int,
     *         total_price: float
     *     }>
     * }
     */
    public function calculatePricing(
        Space $space,
        array $slotTimes,
        array $addOnSelections = [],
        ?array $availableSlots = null,
    ): array {
        if ($slotTimes === []) {
            throw new InvalidArgumentException('Minimal satu slot waktu harus dipilih.');
        }

        // Normalize slot times to H:i
        $normalizedSlotTimes = array_map(
            fn (string $t): string => substr(trim($t), 0, 5),
            $slotTimes
        );

        $matchedSlots = [];
        $totalMinutes = 0;
        $slotSubtotal = 0.0;

        if ($availableSlots !== null) {
            $slotsMap = [];
            foreach ($availableSlots as $slot) {
                $timeKey = substr($slot['start_time'], 0, 5);
                $slotsMap[$timeKey] = $slot;
            }

            foreach ($normalizedSlotTimes as $time) {
                if (! isset($slotsMap[$time])) {
                    throw new InvalidArgumentException("Slot waktu {$time} tidak ditemukan.");
                }
                $slot = $slotsMap[$time];
                $start = CarbonImmutable::parse($slot['start_time']);
                $end = CarbonImmutable::parse($slot['end_time']);
                $diffMinutes = (int) abs($start->diffInMinutes($end));
                $totalMinutes += $diffMinutes;
                $slotSubtotal += (float) $slot['price'];
                $matchedSlots[] = [
                    'start_time' => $slot['start_time'],
                    'end_time' => $slot['end_time'],
                    'price' => (int) $slot['price'],
                ];
            }
        } else {
            // Fallback: estimate from space slot duration or 60 min intervals
            $unitDuration = 60;
            $unitPrice = (float) $space->base_price;

            foreach ($normalizedSlotTimes as $time) {
                $start = CarbonImmutable::parse($time);
                $end = $start->addMinutes($unitDuration);
                $totalMinutes += $unitDuration;
                $slotSubtotal += $unitPrice;
                $matchedSlots[] = [
                    'start_time' => $start->format('H:i:s'),
                    'end_time' => $end->format('H:i:s'),
                    'price' => (int) $unitPrice,
                ];
            }
        }

        $durationHours = max(0.25, round($totalMinutes / 60.0, 2));

        // Process add-ons
        $addonsSubtotal = 0.0;
        $matchedAddOns = [];

        if ($addOnSelections !== []) {
            $addOnIds = array_column($addOnSelections, 'id');
            $addOnModels = SpaceAddOn::query()
                ->where('space_id', $space->id)
                ->where('is_active', true)
                ->whereIn('id', $addOnIds)
                ->get()
                ->keyBy('id');

            foreach ($addOnSelections as $selection) {
                $addOnId = $selection['id'];
                $quantity = max(0, $selection['quantity']);

                if ($quantity <= 0 || ! isset($addOnModels[$addOnId])) {
                    continue;
                }

                /** @var SpaceAddOn $addOn */
                $addOn = $addOnModels[$addOnId];

                if ($addOn->stock_quantity > 0 && $quantity > $addOn->stock_quantity) {
                    throw new InvalidArgumentException("Jumlah untuk layanan {$addOn->name} melebihi stok yang tersedia ({$addOn->stock_quantity}).");
                }

                $unitPrice = (float) $addOn->price;

                $lineTotal = match ($addOn->pricing_type) {
                    AddOnPricingType::PerHour => round($unitPrice * $durationHours * $quantity, 2),
                    AddOnPricingType::PerItem => round($unitPrice * $quantity, 2),
                    AddOnPricingType::PerBooking => round($unitPrice, 2),
                };

                $addonsSubtotal += $lineTotal;

                $matchedAddOns[] = [
                    'space_add_on_id' => $addOn->id,
                    'name' => $addOn->name,
                    'pricing_type' => $addOn->pricing_type->value,
                    'unit_price' => $unitPrice,
                    'quantity' => $addOn->pricing_type === AddOnPricingType::PerBooking ? 1 : $quantity,
                    'total_price' => $lineTotal,
                ];
            }
        }

        $totalAmount = $slotSubtotal + $addonsSubtotal;

        return [
            'duration_minutes' => $totalMinutes,
            'duration_hours' => $durationHours,
            'slot_subtotal' => $slotSubtotal,
            'addons_subtotal' => $addonsSubtotal,
            'total_amount' => $totalAmount,
            'slots' => $matchedSlots,
            'addons' => $matchedAddOns,
        ];
    }

    /**
     * Create a pending booking, reserving slots atomically.
     *
     * @param  array<int, string>  $slotTimes
     * @param  array<int, array{id: int, quantity: int}>  $addOnSelections
     * @param  array{
     *     customer_name: string,
     *     customer_phone: string,
     *     customer_email: string,
     *     notes?: string|null
     * }  $customerData
     */
    public function createBooking(
        User $user,
        Space $space,
        string $date,
        array $slotTimes,
        array $addOnSelections,
        array $customerData,
    ): Booking {
        if (! $space->relationLoaded('venue')) {
            $space->load('venue');
        }

        // Validate date & fetch slots dynamically via generator
        $generated = $this->slotGenerator->generate($space, $date);

        // Build quick lookup for generated slots
        $slotsByTime = [];
        foreach ($generated['slots'] as $s) {
            $timeKey = substr($s['start_time'], 0, 5);
            $slotsByTime[$timeKey] = $s;
        }

        // Validate each chosen slot
        $canonicalCells = [];
        $sortedSlotTimes = $slotTimes;
        sort($sortedSlotTimes);

        foreach ($sortedSlotTimes as $time) {
            $key = substr(trim($time), 0, 5);
            if (! isset($slotsByTime[$key])) {
                throw new InvalidArgumentException("Slot jam {$key} tidak valid.");
            }

            $slotInfo = $slotsByTime[$key];

            if ($slotInfo['status'] !== 'available') {
                throw new SlotConflictException("Slot jam {$key} sudah tidak tersedia.");
            }
            $formatTime = fn (string $t): string => strlen(trim($t)) === 5 ? trim($t).':00' : trim($t);

            $canonicalCells[] = [
                'start_time' => $formatTime($slotInfo['start_time']),
                'end_time' => $formatTime($slotInfo['end_time']),
                'price' => $slotInfo['price'],
            ];
        }

        // Calculate pricing
        $pricing = $this->calculatePricing(
            $space,
            $sortedSlotTimes,
            $addOnSelections,
            $generated['slots']
        );

        $startTime = $canonicalCells[0]['start_time'];
        $endTime = $canonicalCells[count($canonicalCells) - 1]['end_time'];

        $cancellationSnapshot = [
            'policy' => $space->cancellation_policy->value,
            'policy_label' => $space->cancellation_policy->label(),
            'cutoff_hours' => $space->cancellation_cutoff_hours,
            'refund_percentage' => (float) $space->refund_percentage,
        ];

        return DB::transaction(function () use (
            $user,
            $space,
            $date,
            $startTime,
            $endTime,
            $customerData,
            $pricing,
            $cancellationSnapshot,
            $canonicalCells,
        ): Booking {
            $code = $this->generateUniqueCode();
            $qrToken = Str::random(64);

            $booking = Booking::create([
                'venue_id' => $space->venue_id,
                'space_id' => $space->id,
                'user_id' => $user->id,
                'code' => $code,
                'booking_date' => $date,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'customer_name' => $customerData['customer_name'],
                'customer_phone' => $customerData['customer_phone'],
                'customer_email' => $customerData['customer_email'],
                'notes' => $customerData['notes'] ?? null,
                'subtotal_amount' => $pricing['slot_subtotal'],
                'addons_amount' => $pricing['addons_subtotal'],
                'total_amount' => $pricing['total_amount'],
                'status' => BookingStatus::PendingPayment,
                'payment_method' => PaymentMethod::Qris,
                'qr_checkin_token' => $qrToken,
                'expires_at' => now()->addMinutes(10),
                'cancellation_policy_snapshot' => $cancellationSnapshot,
                'refund_amount' => 0,
                'created_by' => $user->id,
            ]);

            // Reserve canonical cells in DB (handles lock & conflict exception)
            $this->slotReservation->reserve(
                bookingId: $booking->id,
                spaceId: $space->id,
                slotDate: $date,
                cells: $canonicalCells,
            );

            // Store add-ons
            foreach ($pricing['addons'] as $addon) {
                BookingAddOn::create([
                    'booking_id' => $booking->id,
                    'space_add_on_id' => $addon['space_add_on_id'],
                    'name' => $addon['name'],
                    'unit_price' => $addon['unit_price'],
                    'quantity' => $addon['quantity'],
                    'total_price' => $addon['total_price'],
                ]);
            }

            return $booking->load(['slots', 'addOns', 'space', 'venue']);
        });
    }

    /**
     * Generate unique booking code ADT-BK-YYYYMMDD-XXXXX.
     */
    protected function generateUniqueCode(): string
    {
        do {
            $code = sprintf(
                'ADT-BK-%s-%s',
                now()->format('Ymd'),
                strtoupper(Str::random(5))
            );
        } while (Booking::query()->where('code', $code)->exists());

        return $code;
    }

    /**
     * Expire a pending booking and release its held slots atomically.
     */
    public function expireBooking(Booking $booking): bool
    {
        return DB::transaction(function () use ($booking): bool {
            /** @var Booking|null $record */
            $record = Booking::query()
                ->where('id', $booking->id)
                ->lockForUpdate()
                ->first();

            if (! $record || $record->status !== BookingStatus::PendingPayment) {
                return false;
            }

            $record->status = BookingStatus::Expired;
            $record->save();

            // Fetch and delete held slots to release unique constraints
            $slots = BookingSlot::query()
                ->where('booking_id', $record->id)
                ->get();

            BookingSlot::query()
                ->where('booking_id', $record->id)
                ->delete();

            // Invalidate slot availability cache
            $flushedKeys = [];
            foreach ($slots as $slot) {
                $cacheKey = "slots:space:{$slot->space_id}:date:{$slot->slot_date->format('Y-m-d')}";
                if (! isset($flushedKeys[$cacheKey])) {
                    Cache::forget($cacheKey);
                    $flushedKeys[$cacheKey] = true;
                }
            }

            // Also flush booking's own space/date in case slots were already removed
            Cache::forget("slots:space:{$record->space_id}:date:{$record->booking_date->format('Y-m-d')}");

            $booking->status = BookingStatus::Expired;

            return true;
        });
    }

    /**
     * Confirm a pending booking and retain locked slots.
     *
     * @param  array<string, mixed>  $paymentDetails
     */
    public function confirmBooking(Booking $booking, array $paymentDetails = []): bool
    {
        $confirmed = DB::transaction(function () use ($booking): bool {
            /** @var Booking|null $record */
            $record = Booking::query()
                ->where('id', $booking->id)
                ->lockForUpdate()
                ->first();

            if (! $record || $record->status !== BookingStatus::PendingPayment) {
                return false;
            }

            if ($record->expires_at !== null && now()->isAfter($record->expires_at)) {
                $this->expireBooking($record);
                $booking->status = BookingStatus::Expired;

                return false;
            }

            $record->status = BookingStatus::Confirmed;
            $record->save();

            // Invalidate slot availability cache for space & date
            Cache::forget("slots:space:{$record->space_id}:date:{$record->booking_date->format('Y-m-d')}");

            $booking->status = BookingStatus::Confirmed;

            return true;
        });

        if ($confirmed) {
            SendBookingConfirmedNotificationJob::dispatch($booking);
        }

        return $confirmed;
    }

    /**
     * Validate and check in a booking at gate.
     *
     * @return array{success: true, message: string, booking: Booking}|array{success: false, error: string, message: string, booking?: Booking}
     */
    public function checkInBooking(
        Venue $venue,
        string $tokenOrCode,
        ?CarbonInterface $scanTime = null,
    ): array {
        $token = trim($tokenOrCode);

        return DB::transaction(function () use ($venue, $token, $scanTime): array {
            /** @var Booking|null $booking */
            $booking = Booking::query()
                ->with(['venue', 'space', 'user'])
                ->where(function ($q) use ($token) {
                    $q->where('qr_checkin_token', $token)
                        ->orWhere('code', $token);
                })
                ->lockForUpdate()
                ->first();

            if (! $booking) {
                return [
                    'success' => false,
                    'error' => 'INVALID_TOKEN',
                    'message' => 'Token atau kode booking tidak ditemukan.',
                ];
            }

            if ($booking->venue_id !== $venue->id) {
                return [
                    'success' => false,
                    'error' => 'WRONG_VENUE',
                    'message' => "Tiket ini terdaftar di {$booking->venue->name}, bukan di {$venue->name}.",
                    'booking' => $booking,
                ];
            }

            if ($booking->status === BookingStatus::CheckedIn) {
                $checkedInTime = $booking->checked_in_at?->timezone($venue->timezone)->format('H:i') ?? '-';
                $checkedInDate = $booking->checked_in_at?->timezone($venue->timezone)->format('d/m/Y') ?? '-';

                return [
                    'success' => false,
                    'error' => 'ALREADY_CHECKED_IN',
                    'message' => "Tiket ini sudah pernah digunakan untuk check-in pada {$checkedInDate} pukul {$checkedInTime} {$venue->timezone}.",
                    'booking' => $booking,
                ];
            }

            if ($booking->status !== BookingStatus::Confirmed) {
                return [
                    'success' => false,
                    'error' => 'NOT_CONFIRMED',
                    'message' => "Status pesanan tidak valid untuk check-in: {$booking->status->label()}.",
                    'booking' => $booking,
                ];
            }

            // Window check: 30 minutes before start_time up to end_time
            $scanAt = ($scanTime ? CarbonImmutable::instance($scanTime) : now())->timezone($venue->timezone);
            $bookingDateStr = $booking->booking_date->format('Y-m-d');
            $windowStart = CarbonImmutable::parse("{$bookingDateStr} {$booking->start_time}", $venue->timezone)->subMinutes(30);
            $windowEnd = CarbonImmutable::parse("{$bookingDateStr} {$booking->end_time}", $venue->timezone);

            if ($scanAt->isBefore($windowStart)) {
                return [
                    'success' => false,
                    'error' => 'TOO_EARLY',
                    'message' => "Check-in belum dibuka. Check-in dapat dilakukan mulai pukul {$windowStart->format('H:i')} (30 menit sebelum jadwal).",
                    'booking' => $booking,
                ];
            }

            if ($scanAt->isAfter($windowEnd)) {
                return [
                    'success' => false,
                    'error' => 'TOO_LATE',
                    'message' => "Waktu bermain telah selesai pada pukul {$windowEnd->format('H:i')} {$venue->timezone}.",
                    'booking' => $booking,
                ];
            }

            $booking->status = BookingStatus::CheckedIn;
            $booking->checked_in_at = Carbon::parse($scanAt);
            $booking->save();

            return [
                'success' => true,
                'message' => "Check-in berhasil! Selamat datang, {$booking->customer_name}.",
                'booking' => $booking,
            ];
        });
    }

    /**
     * Expire past-due pending bookings.
     */
    public function expirePendingBookings(
        ?CarbonInterface $now = null,
        ?int $spaceId = null,
        ?string $slotDate = null,
    ): int {
        $now = $now ?? now();

        $query = Booking::query()
            ->where('status', BookingStatus::PendingPayment)
            ->where('expires_at', '<=', $now);

        if ($spaceId !== null) {
            $query->where('space_id', $spaceId);
        }

        if ($slotDate !== null) {
            $query->where('booking_date', $slotDate);
        }

        $expiredCount = 0;
        foreach ($query->get() as $booking) {
            if ($this->expireBooking($booking)) {
                $expiredCount++;
            }
        }

        return $expiredCount;
    }
}
