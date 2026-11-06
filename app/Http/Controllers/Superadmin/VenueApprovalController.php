<?php

namespace App\Http\Controllers\Superadmin;

use App\Enums\VenueApprovalStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Superadmin\RejectVenueRequest;
use App\Http\Requests\Superadmin\UnpublishVenueRequest;
use App\Models\AuditLog;
use App\Models\Venue;
use App\Services\Audit\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class VenueApprovalController extends Controller
{
    /**
     * Display a listing of venue submissions for curation.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Venue::class);

        $pendingCount = Venue::where('approval_status', VenueApprovalStatus::Pending)->count();
        $approvedCount = Venue::where('approval_status', VenueApprovalStatus::Approved)->count();
        $rejectedCount = Venue::where('approval_status', VenueApprovalStatus::Rejected)->count();
        $totalCount = Venue::count();

        $statusFilter = $request->query('status');
        if (! in_array($statusFilter, ['pending', 'approved', 'rejected', 'all'], true)) {
            $statusFilter = $pendingCount > 0 ? 'pending' : 'all';
        }

        $search = $request->query('search');

        $query = Venue::query()
            ->with(['business.user'])
            ->withCount('spaces')
            ->latest('updated_at');

        if ($statusFilter !== 'all') {
            $query->where('approval_status', $statusFilter);
        }

        if (is_string($search) && trim($search) !== '') {
            $trimmed = trim($search);
            $query->where(function ($q) use ($trimmed) {
                $q->where('name', 'like', "%{$trimmed}%")
                    ->orWhere('city', 'like', "%{$trimmed}%")
                    ->orWhereHas('business', function ($bQ) use ($trimmed) {
                        $bQ->where('name', 'like', "%{$trimmed}%");
                    });
            });
        }

        $venues = $query->paginate(12)->withQueryString();

        return Inertia::render('superadmin/venues/index', [
            'venues' => $venues,
            'filters' => [
                'status' => $statusFilter,
                'search' => $search ?? '',
            ],
            'stats' => [
                'pending_count' => $pendingCount,
                'approved_count' => $approvedCount,
                'rejected_count' => $rejectedCount,
                'total_count' => $totalCount,
            ],
        ]);
    }

    /**
     * Display the detailed venue submission for review.
     */
    public function show(Venue $venue): Response
    {
        Gate::authorize('view', $venue);

        $venue->load([
            'business.user',
            'spaces.schedules',
            'spaces.addOns',
            'spaces.amenities',
        ]);

        $auditLogs = AuditLog::query()
            ->where('auditable_type', $venue->getMorphClass())
            ->where('auditable_id', $venue->id)
            ->with('actor')
            ->latest('created_at')
            ->get();

        return Inertia::render('superadmin/venues/show', [
            'venue' => $venue,
            'auditLogs' => $auditLogs,
        ]);
    }

    /**
     * Approve a venue submission.
     */
    public function approve(Request $request, Venue $venue): RedirectResponse
    {
        Gate::authorize('approve', $venue);

        DB::transaction(function () use ($request, $venue) {
            $venue->update([
                'approval_status' => VenueApprovalStatus::Approved,
                'approved_at' => now(),
                'approved_by' => $request->user()?->id,
                'rejection_reason' => null,
            ]);

            AuditService::log(
                action: 'venue.approved',
                auditable: $venue,
                payload: [
                    'venue_name' => $venue->name,
                    'business' => $venue->business?->name,
                    'owner_email' => $venue->business?->user?->email,
                ],
                actor: $request->user(),
            );
        });

        return back()->with('success', 'Venue berhasil disetujui. Pemilik venue kini dapat mempublikasikannya ke katalog.');
    }

    /**
     * Reject a venue submission with a required reason.
     */
    public function reject(RejectVenueRequest $request, Venue $venue): RedirectResponse
    {
        Gate::authorize('reject', $venue);

        DB::transaction(function () use ($request, $venue) {
            $reason = (string) $request->validated('rejection_reason');

            $venue->update([
                'approval_status' => VenueApprovalStatus::Rejected,
                'rejection_reason' => $reason,
                'is_published' => false,
            ]);

            AuditService::log(
                action: 'venue.rejected',
                auditable: $venue,
                payload: [
                    'venue_name' => $venue->name,
                    'reason' => $reason,
                    'business' => $venue->business?->name,
                ],
                actor: $request->user(),
            );
        });

        return back()->with('success', 'Pengajuan venue berhasil ditolak dengan alasan yang tercatat.');
    }

    /**
     * Unpublish an active venue.
     */
    public function unpublish(UnpublishVenueRequest $request, Venue $venue): RedirectResponse
    {
        Gate::authorize('unpublish', $venue);

        DB::transaction(function () use ($request, $venue) {
            $reason = (string) $request->validated('reason');

            $venue->update([
                'is_published' => false,
            ]);

            AuditService::log(
                action: 'venue.unpublished',
                auditable: $venue,
                payload: [
                    'venue_name' => $venue->name,
                    'reason' => $reason,
                    'business' => $venue->business?->name,
                ],
                actor: $request->user(),
            );
        });

        return back()->with('success', 'Publikasi venue berhasil dicabut dari marketplace.');
    }
}
