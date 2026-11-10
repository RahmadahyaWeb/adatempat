<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operator;

use App\Exceptions\BookingHorizonExceededException;
use App\Http\Controllers\Controller;
use App\Models\Court;
use App\Services\OperatorTimelineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class OperatorTimelineController extends Controller
{
    /**
     * Display the daily court schedule timeline for the operator's assigned venue.
     */
    public function __invoke(Request $request, OperatorTimelineService $timelineService): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'court_id' => ['nullable', 'integer', 'exists:courts,id'],
        ]);

        $courtId = isset($validated['court_id']) ? (int) $validated['court_id'] : null;

        if ($courtId !== null) {
            /** @var Court $court */
            $court = Court::findOrFail($courtId);
            Gate::authorize('view', $court);
        }

        try {
            $timeline = $timelineService->getTimeline(
                operator: $request->user(),
                date: $validated['date'],
                courtId: $courtId,
            );

            return response()->json([
                'status' => 'success',
                'data' => $timeline,
            ]);
        } catch (BookingHorizonExceededException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 403);
        }
    }
}
