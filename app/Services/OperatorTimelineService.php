<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Enums\SlotStatus;
use App\Models\Court;
use App\Models\CourtSlot;
use App\Models\User;
use App\Models\Venue;
use Carbon\CarbonInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;

class OperatorTimelineService
{
    public function __construct(
        protected SlotGenerationService $slotGenerationService
    ) {}

    /**
     * Retrieve daily court schedule timeline for an operator's venue.
     *
     * @return array<string, mixed>
     */
    public function getTimeline(User $operator, CarbonInterface|string $date, ?int $courtId = null): array
    {
        if (! $operator->isVenueOperator() || $operator->venue_id === null) {
            throw new HttpException(403, 'Unauthorized venue operator.');
        }

        /** @var Venue $venue */
        $venue = $operator->venue ?? Venue::findOrFail($operator->venue_id);

        $targetDate = $this->slotGenerationService->validateBookingHorizon($venue, $date);
        $dateString = $targetDate->toDateString();

        $courtsQuery = Court::query()
            ->where('venue_id', $venue->id)
            ->where('is_active', true);

        if ($courtId !== null) {
            $courtsQuery->where('id', $courtId);
        }

        $courts = $courtsQuery->orderBy('id')->get();

        // Ensure slots exist for all selected courts for this date (JIT generation)
        foreach ($courts as $court) {
            $this->slotGenerationService->generateSlotsForCourtAndDate($court, $targetDate);
        }

        // Load court slots with booking items and booking data
        $courtIds = $courts->pluck('id')->all();
        $slots = CourtSlot::query()
            ->whereIn('court_id', $courtIds)
            ->where('date', $dateString)
            ->with([
                'bookingItems.booking.user',
            ])
            ->orderBy('start_time')
            ->get()
            ->groupBy('court_id');

        $now = now();
        $courtsData = [];

        foreach ($courts as $court) {
            $courtSlots = $slots->get($court->id, collect());
            $formattedSlots = [];

            foreach ($courtSlots as $slot) {
                $effectiveStatus = $slot->resolveEffectiveStatus($now);
                $bookingData = null;

                if ($effectiveStatus === SlotStatus::Booked) {
                    $activeItem = $slot->bookingItems->first(function ($item) {
                        return in_array($item->booking?->payment_status, [
                            PaymentStatus::Paid,
                            PaymentStatus::Rescheduled,
                            PaymentStatus::Completed,
                        ], true);
                    });

                    if ($activeItem && $activeItem->booking) {
                        $booking = $activeItem->booking;
                        $bookingData = [
                            'booking_code' => $booking->booking_code,
                            'player_id' => $booking->user_id,
                            'player_name' => $booking->user?->name,
                            'player_email' => $booking->user?->email,
                            'customer_id' => $booking->user_id,
                            'customer_name' => $booking->user?->name,
                            'customer_email' => $booking->user?->email,
                            'payment_status' => $booking->payment_status->value,
                            'check_in_status' => $booking->check_in_status->value,
                            'checked_in_at' => $booking->checked_in_at?->toISOString(),
                        ];
                    }
                }

                $formattedSlots[] = [
                    'id' => $slot->id,
                    'court_id' => $slot->court_id,
                    'start_time' => $slot->start_time,
                    'end_time' => $slot->end_time,
                    'price' => (int) $slot->price,
                    'status' => $effectiveStatus->value,
                    'block_reason' => $slot->block_reason,
                    'booking' => $bookingData,
                ];
            }

            $courtsData[] = [
                'id' => $court->id,
                'name' => $court->name,
                'sport_type' => $court->sport_type?->value,
                'slots' => $formattedSlots,
            ];
        }

        return [
            'venue' => [
                'id' => $venue->id,
                'name' => $venue->name,
            ],
            'date' => $dateString,
            'courts' => $courtsData,
        ];
    }
}
