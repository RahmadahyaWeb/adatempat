<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Models\Venue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminVenueController extends Controller
{
    /**
     * Onboard a new venue onto the platform.
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
        ]);

        if (empty($validated['slug'])) {
            $baseSlug = Str::slug($validated['name']);
            $slug = $baseSlug;
            $counter = 1;

            while (Venue::where('slug', $slug)->exists()) {
                $slug = "{$baseSlug}-{$counter}";
                $counter++;
            }

            $validated['slug'] = $slug;
        }

        $venue = Venue::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Venue onboarded successfully.',
            'data' => $venue,
        ], 201);
    }
}
