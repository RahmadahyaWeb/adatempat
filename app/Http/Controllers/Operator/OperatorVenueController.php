<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\Venue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class OperatorVenueController extends Controller
{
    /**
     * Display the operator's assigned venue profile.
     */
    public function show(Request $request): JsonResponse
    {
        $venue = $this->resolveOperatorVenue($request);

        Gate::authorize('view', $venue);

        return response()->json([
            'status' => 'success',
            'data' => $venue,
        ]);
    }

    /**
     * Update the operator's assigned venue profile.
     */
    public function update(Request $request): JsonResponse
    {
        $venue = $this->resolveOperatorVenue($request);

        Gate::authorize('update', $venue);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string'],
            'phone' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'facilities' => ['nullable', 'array'],
            'facilities.*' => ['string'],
            'booking_horizon_days' => ['required', 'integer', 'min:1', 'max:365'],
            'allow_reschedule' => ['required', 'boolean'],
        ]);

        $venue->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Venue profile updated successfully.',
            'data' => $venue->fresh(),
        ]);
    }

    /**
     * Resolve the assigned venue strictly from authenticated user.
     */
    private function resolveOperatorVenue(Request $request): Venue
    {
        $venue = $request->user()?->venue;

        if (! $venue) {
            abort(403, 'Access denied. Operator is not assigned to any venue.');
        }

        return $venue;
    }
}
