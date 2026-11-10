<?php

declare(strict_types=1);

namespace App\Http\Controllers\Player;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PlayerTicketController extends Controller
{
    /**
     * Display a paginated listing of the player's bookings and digital tickets.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $bookings = Booking::query()
            ->where('user_id', $user->id)
            ->with(['venue', 'items.courtSlot.court'])
            ->latest('id')
            ->paginate(15);

        $bookings->through(function (Booking $booking) {
            return [
                'booking_code' => $booking->booking_code,
                'venue' => [
                    'id' => $booking->venue->id,
                    'name' => $booking->venue->name,
                    'city' => $booking->venue->city,
                ],
                'total_amount' => $booking->total_amount,
                'payment_status' => $booking->payment_status->value,
                'check_in_status' => $booking->check_in_status->value,
                'qr_token' => $booking->canRevealTicket() ? $booking->qr_token : null,
                'created_at' => $booking->created_at?->toISOString(),
                'items' => $booking->items->map(fn ($item) => [
                    'court_id' => $item->courtSlot?->court_id,
                    'court_name' => $item->courtSlot?->court?->name,
                    'sport_type' => $item->courtSlot?->court?->sport_type?->value,
                    'date' => $item->courtSlot?->date?->toDateString(),
                    'start_time' => $item->courtSlot?->start_time,
                    'end_time' => $item->courtSlot?->end_time,
                    'price_locked' => $item->price_locked,
                ]),
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $bookings,
        ]);
    }

    /**
     * Display the specified digital ticket.
     */
    public function show(Request $request, Booking $booking): JsonResponse
    {
        Gate::authorize('view', $booking);

        $booking->load(['venue', 'items.courtSlot.court']);

        return response()->json([
            'status' => 'success',
            'data' => [
                'booking_code' => $booking->booking_code,
                'payment_status' => $booking->payment_status->value,
                'check_in_status' => $booking->check_in_status->value,
                'total_amount' => $booking->total_amount,
                'paid_at' => $booking->paid_at?->toISOString(),
                'checked_in_at' => $booking->checked_in_at?->toISOString(),
                'qr_token' => $booking->canRevealTicket() ? $booking->qr_token : null,
                'venue' => [
                    'id' => $booking->venue->id,
                    'name' => $booking->venue->name,
                    'slug' => $booking->venue->slug,
                    'city' => $booking->venue->city,
                    'address' => $booking->venue->address,
                    'phone' => $booking->venue->phone,
                ],
                'items' => $booking->items->map(fn ($item) => [
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
