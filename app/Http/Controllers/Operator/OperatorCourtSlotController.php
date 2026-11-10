<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operator;

use App\Exceptions\BookingHorizonExceededException;
use App\Http\Controllers\Controller;
use App\Models\Court;
use App\Services\CourtSlotAvailabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class OperatorCourtSlotController extends Controller
{
    /**
     * Query court slots for a specific date, generating missing slots just-in-time.
     * Enforces booking horizon and tenant authorization at the policy boundary.
     */
    public function index(Request $request, Court $court, CourtSlotAvailabilityService $availabilityService): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        // Policy boundary check for tenant ownership and booking horizon
        Gate::authorize('viewSlots', [$court, $validated['date']]);

        try {
            $slots = $availabilityService->getSlotsForCourt($court, $validated['date']);

            return response()->json([
                'status' => 'success',
                'data' => $slots,
            ]);
        } catch (BookingHorizonExceededException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 403);
        }
    }
}
