<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Models\Venue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminVenueSubscriptionController extends Controller
{
    /**
     * Update a venue's SaaS subscription status and expiration.
     */
    public function update(Request $request, Venue $venue): JsonResponse
    {
        $validated = $request->validate([
            'subscription_status' => ['required', Rule::enum(SubscriptionStatus::class)],
            'subscription_expires_at' => ['nullable', 'date'],
        ]);

        $venue->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Venue subscription updated successfully.',
            'data' => $venue->fresh(),
        ]);
    }
}
