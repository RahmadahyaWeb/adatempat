<?php

declare(strict_types=1);

namespace App\Http\Controllers\Player;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\RescheduleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PlayerRescheduleController extends Controller
{
    public function __construct(
        protected RescheduleService $rescheduleService,
    ) {}

    /**
     * Reschedule an existing paid booking to an alternative court slot.
     */
    public function __invoke(Request $request, Booking $booking): JsonResponse
    {
        Gate::authorize('reschedule', $booking);

        $validated = $request->validate([
            'replacement_slot_id' => ['required', 'integer'],
            'original_slot_id' => ['nullable', 'integer'],
        ]);

        $rescheduledBooking = $this->rescheduleService->reschedule(
            player: $request->user(),
            booking: $booking,
            replacementSlotId: (int) $validated['replacement_slot_id'],
            originalSlotId: isset($validated['original_slot_id']) ? (int) $validated['original_slot_id'] : null,
        );

        $rescheduledBooking->loadMissing(['venue', 'items.courtSlot.court']);

        return response()->json([
            'status' => 'success',
            'message' => 'Booking rescheduled successfully.',
            'data' => [
                'booking_code' => $rescheduledBooking->booking_code,
                'payment_status' => $rescheduledBooking->payment_status->value,
                'check_in_status' => $rescheduledBooking->check_in_status->value,
                'total_amount' => $rescheduledBooking->total_amount,
                'paid_at' => $rescheduledBooking->paid_at?->toISOString(),
                'checked_in_at' => $rescheduledBooking->checked_in_at?->toISOString(),
                'rescheduled_at' => $rescheduledBooking->rescheduled_at?->toISOString(),
                'qr_token' => $rescheduledBooking->canRevealTicket() ? $rescheduledBooking->qr_token : null,
                'venue' => [
                    'id' => $rescheduledBooking->venue->id,
                    'name' => $rescheduledBooking->venue->name,
                    'slug' => $rescheduledBooking->venue->slug,
                    'city' => $rescheduledBooking->venue->city,
                    'address' => $rescheduledBooking->venue->address,
                    'phone' => $rescheduledBooking->venue->phone,
                ],
                'items' => $rescheduledBooking->items->map(fn ($item) => [
                    'court_id' => $item->courtSlot?->court_id,
                    'court_name' => $item->courtSlot?->court?->name,
                    'sport_type' => $item->courtSlot?->court?->sport_type?->value,
                    'date' => $item->courtSlot?->date?->toDateString(),
                    'start_time' => $item->courtSlot?->start_time,
                    'end_time' => $item->courtSlot?->end_time,
                    'price_locked' => $item->price_locked,
                ]),
            ],
        ]);
    }
}
