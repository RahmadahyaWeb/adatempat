<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Exceptions\BookingHorizonExceededException;
use App\Http\Controllers\Controller;
use App\Models\Court;
use App\Services\CourtSlotAvailabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicCourtSlotController extends Controller
{
    /**
     * Retrieve chronological 60-minute slot availability for a specific court and calendar date.
     * Delegates to CourtSlotAvailabilityService for transparent JIT generation and covering index retrieval.
     */
    public function index(Request $request, Court $court, CourtSlotAvailabilityService $availabilityService): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        if (! $court->is_active) {
            return response()->json([
                'status' => 'error',
                'message' => 'Court not found or currently unavailable.',
            ], 404);
        }

        $venue = $court->relationLoaded('venue') ? $court->venue : $court->venue()->first();
        if (! $venue || ! $venue->hasActiveSubscription()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Venue is currently unavailable.',
            ], 404);
        }

        try {
            $slots = $availabilityService->getAvailableSlots($court, $validated['date']);

            return response()->json([
                'status' => 'success',
                'data' => $slots,
            ]);
        } catch (BookingHorizonExceededException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
