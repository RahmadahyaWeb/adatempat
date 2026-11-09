<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operator;

use App\Enums\SportType;
use App\Http\Controllers\Controller;
use App\Models\Court;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class OperatorCourtController extends Controller
{
    /**
     * List all courts scoped to the authenticated operator's venue.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Court::class);

        $venueId = $this->resolveOperatorVenueId($request);

        return response()->json([
            'status' => 'success',
            'data' => Court::where('venue_id', $venueId)->get(),
        ]);
    }

    /**
     * Create a new court under the operator's assigned venue.
     */
    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Court::class);

        $venueId = $this->resolveOperatorVenueId($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sport_type' => ['required', Rule::enum(SportType::class)],
            'opening_time' => ['nullable', 'date_format:H:i,H:i:s'],
            'closing_time' => ['nullable', 'date_format:H:i,H:i:s', 'after:opening_time'],
            'default_hourly_rate' => ['required', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['venue_id'] = $venueId;
        $validated['opening_time'] = $validated['opening_time'] ?? '06:00:00';
        $validated['closing_time'] = $validated['closing_time'] ?? '23:00:00';
        $validated['is_active'] = $validated['is_active'] ?? true;

        $court = Court::create($validated);

        return response()->json([
            'status' => 'success',
            'data' => $court,
        ], 201);
    }

    /**
     * Display the specified court details.
     */
    public function show(Court $court): JsonResponse
    {
        Gate::authorize('view', $court);

        return response()->json([
            'status' => 'success',
            'data' => $court,
        ]);
    }

    /**
     * Update the specified court.
     */
    public function update(Request $request, Court $court): JsonResponse
    {
        Gate::authorize('update', $court);

        $targetOpening = $request->input('opening_time', $court->opening_time);
        $targetClosing = $request->input('closing_time', $court->closing_time);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'sport_type' => ['sometimes', 'required', Rule::enum(SportType::class)],
            'opening_time' => ['sometimes', 'required', 'date_format:H:i,H:i:s'],
            'closing_time' => [
                'sometimes',
                'required',
                'date_format:H:i,H:i:s',
                function (string $attribute, mixed $value, \Closure $fail) use ($targetOpening) {
                    if (is_string($value) && is_string($targetOpening) && strtotime($value) <= strtotime($targetOpening)) {
                        $fail('The closing time must be a date after opening time.');
                    }
                },
            ],
            'default_hourly_rate' => ['sometimes', 'required', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'required', 'boolean'],
        ]);

        if ($request->has('opening_time') && ! $request->has('closing_time')) {
            if (strtotime((string) $targetOpening) >= strtotime((string) $targetClosing)) {
                return response()->json([
                    'message' => 'The opening time must be before closing time.',
                    'errors' => ['opening_time' => ['The opening time must be before closing time.']],
                ], 422);
            }
        }

        $court->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Court updated successfully.',
            'data' => $court->fresh(),
        ]);
    }

    /**
     * Soft delete the specified court.
     */
    public function destroy(Court $court): JsonResponse
    {
        Gate::authorize('delete', $court);

        $court->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Court deleted successfully.',
        ]);
    }

    /**
     * Strictly resolve venue ID from authenticated operator.
     */
    private function resolveOperatorVenueId(Request $request): int
    {
        $venueId = $request->user()?->venue_id;

        if ($venueId === null) {
            abort(403, 'Access denied. Operator is not assigned to any venue.');
        }

        return (int) $venueId;
    }
}
