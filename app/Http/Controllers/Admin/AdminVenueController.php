<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Models\Venue;
use App\Services\VenueManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminVenueController extends Controller
{
    public function __construct(
        protected VenueManagementService $venueManagementService,
    ) {}

    /**
     * List all platform venues with subscription and operator details.
     */
    public function index(): JsonResponse
    {
        $venues = Venue::with(['operators', 'courts'])->orderBy('name')->get();

        return response()->json([
            'status' => 'success',
            'data' => $venues,
        ]);
    }

    /**
     * Onboard a new venue onto the platform with optional operator linking.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:venues,slug'],
            'city' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string'],
            'phone' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'facilities' => ['nullable', 'array'],
            'facilities.*' => ['string'],
            'booking_horizon_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'allow_reschedule' => ['nullable', 'boolean'],
            'subscription_status' => ['nullable', Rule::enum(SubscriptionStatus::class)],
            'subscription_expires_at' => ['nullable', 'date'],
            'operator_id' => ['nullable', 'integer', 'exists:users,id'],
            'operator_email' => ['nullable', 'email'],
        ]);

        $operatorId = isset($validated['operator_id']) ? (int) $validated['operator_id'] : null;
        $operatorEmail = isset($validated['operator_email']) ? (string) $validated['operator_email'] : null;
        unset($validated['operator_id'], $validated['operator_email']);

        $venue = $this->venueManagementService->onboardVenue(
            $validated,
            $operatorId,
            $operatorEmail,
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Venue onboarded successfully.',
            'data' => $venue,
        ], 201);
    }

    /**
     * Inspect a specific venue profile.
     */
    public function show(Venue $venue): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $venue->load(['operators', 'courts']),
        ]);
    }
}
