<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Enums\VenueApprovalStatus;
use App\Models\Business;
use App\Models\Venue;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        $ownerData = null;
        $superadminData = null;

        if ($user && ($user->role === UserRole::Owner || $user->role === UserRole::Superadmin)) {
            $business = $user->businesses()->first();

            if ($business) {
                $venues = Venue::query()
                    ->where('business_id', $business->id)
                    ->withCount('spaces')
                    ->latest('updated_at')
                    ->get();

                $ownerData = [
                    'business_name' => $business->name,
                    'stats' => [
                        'total_venues' => $venues->count(),
                        'approved_count' => $venues->where('approval_status', VenueApprovalStatus::Approved)->count(),
                        'pending_count' => $venues->where('approval_status', VenueApprovalStatus::Pending)->count(),
                        'rejected_count' => $venues->where('approval_status', VenueApprovalStatus::Rejected)->count(),
                        'published_count' => $venues->where('is_published', true)->count(),
                    ],
                    'recent_venues' => $venues->take(6)->map(fn (Venue $v) => [
                        'id' => $v->id,
                        'name' => $v->name,
                        'slug' => $v->slug,
                        'city' => $v->city,
                        'timezone' => $v->timezone,
                        'contact_phone' => $v->contact_phone,
                        'is_published' => (bool) $v->is_published,
                        'approval_status' => $v->approval_status->value,
                        'rejection_reason' => $v->rejection_reason,
                        'cover_url' => $v->cover_url,
                        'spaces_count' => (int) $v->spaces_count,
                        'updated_at' => $v->updated_at?->diffForHumans() ?? 'Baru saja',
                    ])->values()->all(),
                ];
            }
        }

        if ($user && $user->role === UserRole::Superadmin) {
            $allVenues = Venue::query()->with('business')->latest('created_at')->get();
            $pendingVenues = $allVenues->where('approval_status', VenueApprovalStatus::Pending);

            $superadminData = [
                'stats' => [
                    'pending_venues_count' => $pendingVenues->count(),
                    'approved_venues_count' => $allVenues->where('approval_status', VenueApprovalStatus::Approved)->count(),
                    'rejected_venues_count' => $allVenues->where('approval_status', VenueApprovalStatus::Rejected)->count(),
                    'total_venues' => $allVenues->count(),
                    'total_businesses' => Business::query()->count(),
                ],
                'pending_venues' => $pendingVenues->take(6)->map(fn (Venue $v) => [
                    'id' => $v->id,
                    'name' => $v->name,
                    'slug' => $v->slug,
                    'city' => $v->city,
                    'timezone' => $v->timezone,
                    'contact_phone' => $v->contact_phone,
                    'business_name' => $v->business->name,
                    'created_at' => $v->created_at?->diffForHumans() ?? 'Baru saja',
                    'cover_url' => $v->cover_url,
                ])->values()->all(),
            ];
        }

        return Inertia::render('dashboard', [
            'ownerData' => $ownerData,
            'superadminData' => $superadminData,
        ]);
    }
}
