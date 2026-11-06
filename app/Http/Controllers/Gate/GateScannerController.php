<?php

namespace App\Http\Controllers\Gate;

use App\Enums\BookingStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Venue;
use App\Services\Bookings\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GateScannerController extends Controller
{
    public function __construct(
        protected BookingService $bookingService,
    ) {}

    /**
     * Display the gate scanner page.
     */
    public function show(Request $request): Response
    {
        $user = $request->user();

        if ($user->role === UserRole::Superadmin) {
            $venues = Venue::query()
                ->orderBy('name')
                ->get();
        } else {
            $businessIds = $user->businesses()->pluck('id');
            $venues = Venue::query()
                ->whereIn('business_id', $businessIds)
                ->orderBy('name')
                ->get();
        }

        $selectedVenueId = $request->integer('venue_id');
        /** @var Venue|null $selectedVenue */
        $selectedVenue = $venues->firstWhere('id', $selectedVenueId) ?? $venues->first();

        $recentScans = [];
        if ($selectedVenue) {
            $recentScans = Booking::query()
                ->with('space')
                ->where('venue_id', $selectedVenue->id)
                ->where('status', BookingStatus::CheckedIn)
                ->whereNotNull('checked_in_at')
                ->orderByDesc('checked_in_at')
                ->limit(5)
                ->get()
                ->map(fn (Booking $b) => [
                    'code' => $b->code,
                    'customer_name' => $b->customer_name,
                    'space_name' => $b->space->name,
                    'booking_date' => $b->booking_date->format('Y-m-d'),
                    'start_time' => substr($b->start_time, 0, 5),
                    'end_time' => substr($b->end_time, 0, 5),
                    'checked_in_at' => $b->checked_in_at?->timezone($selectedVenue->timezone)->format('H:i'),
                ])
                ->all();
        }

        return Inertia::render('gate/scanner', [
            'venues' => $venues->map(fn (Venue $v) => [
                'id' => $v->id,
                'name' => $v->name,
                'city' => $v->city,
                'timezone' => $v->timezone,
            ])->values()->all(),
            'selected_venue_id' => $selectedVenue?->id,
            'recent_scans' => $recentScans,
        ]);
    }

    /**
     * Process check-in token scan.
     */
    public function scan(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'venue_id' => ['required', 'integer', 'exists:venues,id'],
            'token' => ['required', 'string', 'max:255'],
        ]);

        /** @var Venue $venue */
        $venue = Venue::findOrFail($validated['venue_id']);
        $user = $request->user();

        if ($user->role !== UserRole::Superadmin) {
            $userBusinessIds = $user->businesses()->pluck('id')->all();
            if (! in_array($venue->business_id, $userBusinessIds, true)) {
                abort(403, 'Anda tidak memiliki hak akses operasional untuk venue ini.');
            }
        }

        $result = $this->bookingService->checkInBooking($venue, $validated['token']);

        if (! $result['success']) {
            return response()->json([
                'success' => false,
                'error' => $result['error'],
                'message' => $result['message'],
                'booking' => isset($result['booking']) ? [
                    'code' => $result['booking']->code,
                    'customer_name' => $result['booking']->customer_name,
                    'space_name' => $result['booking']->space->name,
                ] : null,
            ], 422);
        }

        $booking = $result['booking'];

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'booking' => [
                'id' => $booking->id,
                'code' => $booking->code,
                'customer_name' => $booking->customer_name,
                'customer_phone' => $booking->customer_phone,
                'space_name' => $booking->space->name,
                'booking_date' => $booking->booking_date->format('Y-m-d'),
                'start_time' => substr($booking->start_time, 0, 5),
                'end_time' => substr($booking->end_time, 0, 5),
                'checked_in_at' => $booking->checked_in_at?->timezone($venue->timezone)->format('d M Y H:i'),
            ],
        ]);
    }
}
