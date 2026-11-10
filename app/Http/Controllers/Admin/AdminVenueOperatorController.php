<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Venue;
use App\Services\VenueManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminVenueOperatorController extends Controller
{
    public function __construct(
        protected VenueManagementService $venueManagementService,
    ) {}

    /**
     * List all venue operators linked to this venue.
     */
    public function index(Venue $venue): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $venue->operators,
        ]);
    }

    /**
     * Link an operator user account to this venue.
     */
    public function store(Request $request, Venue $venue): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required_without:email', 'nullable', 'integer', 'exists:users,id'],
            'email' => ['required_without:user_id', 'nullable', 'email'],
        ]);

        $userId = isset($validated['user_id']) ? (int) $validated['user_id'] : null;
        $email = isset($validated['email']) ? (string) $validated['email'] : null;

        $linkedOperator = $this->venueManagementService->linkOperatorByCredentials(
            $venue,
            $userId,
            $email,
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Venue operator linked successfully.',
            'data' => $linkedOperator,
        ], 201);
    }

    /**
     * Unlink an operator from this venue.
     */
    public function destroy(Venue $venue, User $user): JsonResponse
    {
        $unlinkedOperator = $this->venueManagementService->unlinkOperator($venue, $user);

        return response()->json([
            'status' => 'success',
            'message' => 'Venue operator unlinked successfully.',
            'data' => $unlinkedOperator,
        ]);
    }
}
