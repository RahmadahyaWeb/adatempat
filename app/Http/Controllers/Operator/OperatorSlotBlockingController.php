<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\CourtSlot;
use App\Services\SlotBlockingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class OperatorSlotBlockingController extends Controller
{
    /**
     * Block an available court slot for walk-in or maintenance.
     */
    public function block(Request $request, CourtSlot $slot, SlotBlockingService $service): JsonResponse
    {
        Gate::authorize('block', $slot);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $blockedSlot = $service->blockSlot($request->user(), $slot, $validated['reason'] ?? null);

        return response()->json([
            'status' => 'success',
            'message' => 'Court slot blocked successfully.',
            'data' => $blockedSlot,
        ]);
    }

    /**
     * Unblock a previously blocked court slot.
     */
    public function unblock(Request $request, CourtSlot $slot, SlotBlockingService $service): JsonResponse
    {
        Gate::authorize('unblock', $slot);

        $unblockedSlot = $service->unblockSlot($request->user(), $slot);

        return response()->json([
            'status' => 'success',
            'message' => 'Court slot unblocked successfully.',
            'data' => $unblockedSlot,
        ]);
    }
}
