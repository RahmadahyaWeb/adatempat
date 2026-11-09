<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Court;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicVenueController extends Controller
{
    /**
     * Discover active curated sports venues with optional city and sport type filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $venues = Venue::query()
            ->publiclyDiscoverable()
            ->when($request->filled('city'), function (Builder $query) use ($request) {
                $query->where('city', $request->query('city'));
            })
            ->when($request->filled('sport_type'), function (Builder $query) use ($request) {
                $sport = (string) $request->query('sport_type');
                $query->whereHas('courts', function (Builder $courtQuery) use ($sport) {
                    $courtQuery->where('is_active', true)
                        ->where('sport_type', $sport);
                });
            })
            ->with(['courts' => function (HasMany $query) {
                $query->where('is_active', true);
            }])
            ->orderBy('name')
            ->get();

        $data = $venues->map(fn (Venue $venue): array => [
            'id' => $venue->id,
            'name' => $venue->name,
            'slug' => $venue->slug,
            'city' => $venue->city,
            'address' => $venue->address,
            'phone' => $venue->phone,
            'description' => $venue->description,
            'facilities' => $venue->facilities ?? [],
            'booking_horizon_days' => $venue->booking_horizon_days,
            'allow_reschedule' => $venue->allow_reschedule,
            'sports' => $venue->activeSports(),
            'starting_price' => $venue->startingPrice(),
            'courts_count' => $venue->courts->where('is_active', true)->count(),
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }

    /**
     * Retrieve full public venue profile details including facilities and active court listings.
     */
    public function show(string $slug): JsonResponse
    {
        $venue = Venue::where('slug', $slug)
            ->publiclyDiscoverable()
            ->with(['courts' => function (HasMany $courtQuery) {
                $courtQuery->where('is_active', true)->orderBy('name');
            }])
            ->first();

        if (! $venue) {
            return response()->json([
                'status' => 'error',
                'message' => 'Venue not found or currently unavailable.',
            ], 404);
        }

        $activeCourts = $venue->courts->where('is_active', true);
        $minOpening = $activeCourts->min('opening_time');
        $maxClosing = $activeCourts->max('closing_time');

        return response()->json([
            'status' => 'success',
            'data' => [
                'id' => $venue->id,
                'name' => $venue->name,
                'slug' => $venue->slug,
                'city' => $venue->city,
                'address' => $venue->address,
                'phone' => $venue->phone,
                'description' => $venue->description,
                'facilities' => $venue->facilities ?? [],
                'booking_horizon_days' => $venue->booking_horizon_days,
                'allow_reschedule' => $venue->allow_reschedule,
                'sports' => $venue->activeSports(),
                'operational_hours' => [
                    'opening_time' => $minOpening,
                    'closing_time' => $maxClosing,
                ],
                'courts' => $activeCourts->map(fn (Court $court): array => [
                    'id' => $court->id,
                    'name' => $court->name,
                    'sport_type' => is_object($court->sport_type) ? $court->sport_type->value : (string) $court->sport_type,
                    'opening_time' => (string) $court->opening_time,
                    'closing_time' => (string) $court->closing_time,
                    'default_hourly_rate' => (int) $court->default_hourly_rate,
                ])->values()->all(),
            ],
        ]);
    }
}
