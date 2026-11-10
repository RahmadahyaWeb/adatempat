<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Services\BookingConcurrencyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    /**
     * Acquire multi-slot atomic locks and create a pending draft booking.
     */
    public function hold(Request $request, BookingConcurrencyService $concurrencyService): JsonResponse
    {
        $validated = $request->validate([
            'slot_ids' => ['required', 'array', 'min:1'],
            'slot_ids.*' => ['integer', 'exists:court_slots,id'],
            'court_id' => ['nullable', 'integer', 'exists:courts,id'],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $booking = $concurrencyService->holdSlots(
            player: $request->user(),
            slotIds: $validated['slot_ids'],
            courtId: isset($validated['court_id']) ? (int) $validated['court_id'] : null,
            date: $validated['date'] ?? null,
        );

        return response()->json([
            'status' => 'success',
            'data' => [
                'booking_code' => $booking->booking_code,
                'total_amount' => $booking->total_amount,
                'payment_status' => $booking->payment_status->value,
                'expires_at' => $booking->qris_expires_at?->toISOString() ?? now()->addSeconds(600)->toISOString(),
                'items' => $booking->items->map(fn (BookingItem $item): array => [
                    'court_slot_id' => $item->court_slot_id,
                    'price' => $item->price,
                    'start_time' => (string) $item->courtSlot?->start_time,
                    'end_time' => (string) $item->courtSlot?->end_time,
                ])->values()->all(),
            ],
        ], 201);
    }

    /**
     * Explicitly abandon checkout, releasing hold locks and database slot statuses.
     */
    public function abandon(Request $request, Booking $booking, BookingConcurrencyService $concurrencyService): JsonResponse
    {
        $user = $request->user();
        if ($booking->user_id !== $user->id && ! $user->isSuperadmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You are not authorized to abandon this booking hold.',
            ], 403);
        }

        if ($booking->payment_status === PaymentStatus::Pending) {
            $concurrencyService->abandonHold($booking);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Checkout hold has been abandoned.',
        ]);
    }
}
