<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Services\CheckInVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OperatorCheckInController extends Controller
{
    /**
     * Handle the incoming front-desk check-in request.
     */
    public function __invoke(Request $request, CheckInVerificationService $service): JsonResponse
    {
        $validated = $request->validate([
            'qr_token' => ['nullable', 'string', 'required_without:booking_code'],
            'booking_code' => ['nullable', 'string', 'required_without:qr_token'],
        ]);

        $booking = $service->verifyAndCheckIn(
            operator: $request->user(),
            qrToken: $validated['qr_token'] ?? null,
            bookingCode: $validated['booking_code'] ?? null,
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Player check-in verified successfully.',
            'data' => [
                'booking_code' => $booking->booking_code,
                'check_in_status' => $booking->check_in_status->value,
                'checked_in_at' => $booking->checked_in_at?->toISOString(),
                'checked_in_by' => $booking->checked_in_by,
                'staff_name' => $booking->checkedInBy?->name,
                'venue_name' => $booking->venue?->name,
                'items' => $booking->items->map(fn ($item) => [
                    'court_name' => $item->courtSlot?->court?->name,
                    'sport_type' => $item->courtSlot?->court?->sport_type?->value,
                    'date' => $item->courtSlot?->date?->toDateString(),
                    'start_time' => $item->courtSlot?->start_time,
                    'end_time' => $item->courtSlot?->end_time,
                ]),
            ],
        ]);
    }
}
