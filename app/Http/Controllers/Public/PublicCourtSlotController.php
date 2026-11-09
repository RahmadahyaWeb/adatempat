<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Exceptions\BookingHorizonExceededException;
use App\Http\Controllers\Controller;
use App\Models\Court;
use App\Models\CourtSlot;
use App\Services\SlotGenerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PublicCourtSlotController extends Controller
{
    /**
     * Retrieve chronological 60-minute slot availability for a specific court and calendar date.
     * Triggers transparent JIT slot generation and utilizes MySQL covering index.
     */
    public function index(Request $request, Court $court, SlotGenerationService $slotGenerationService): JsonResponse
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

        $dateString = $validated['date'];

        try {
            $slotGenerationService->validateBookingHorizon($venue, $dateString);
            $slotGenerationService->generateSlotsForCourtAndDate($court, $dateString);
        } catch (BookingHorizonExceededException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }

        // Utilize the specialized covering index idx_slots_availability for an index-only scan
        $slots = CourtSlot::query()
            ->select(['id', 'court_id', 'date', 'status', 'start_time', 'end_time', 'price', 'locked_until'])
            ->from(DB::raw('court_slots FORCE INDEX (idx_slots_availability)'))
            ->where('court_id', $court->id)
            ->where('date', $dateString)
            ->orderBy('start_time', 'asc')
            ->get();

        $now = now();

        $data = $slots->map(fn (CourtSlot $slot): array => [
            'id' => $slot->id,
            'court_id' => $slot->court_id,
            'date' => is_object($slot->date) ? $slot->date->format('Y-m-d') : (string) $slot->date,
            'start_time' => (string) $slot->start_time,
            'end_time' => (string) $slot->end_time,
            'price' => (int) $slot->price,
            'status' => $slot->resolveEffectiveStatus($now)->value,
            'is_bookable' => $slot->isBookable($now),
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }
}
