<?php

namespace App\Http\Controllers\Superadmin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    /**
     * Display a paginated audit trail of platform events.
     */
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->role === UserRole::Superadmin, 403);

        $category = $request->query('category', 'all');
        $search = $request->query('search');

        $query = AuditLog::query()
            ->with('actor')
            ->latest('created_at');

        if ($category === 'venue') {
            $query->where('action', 'like', 'venue.%');
        } elseif ($category === 'security') {
            $query->where('action', 'like', 'user.%');
        }

        if (is_string($search) && trim($search) !== '') {
            $trimmed = trim($search);
            $query->where(function ($q) use ($trimmed) {
                $q->where('action', 'like', "%{$trimmed}%")
                    ->orWhere('payload', 'like', "%{$trimmed}%")
                    ->orWhere('ip_address', 'like', "%{$trimmed}%")
                    ->orWhereHas('actor', function ($actorQ) use ($trimmed) {
                        $actorQ->where('name', 'like', "%{$trimmed}%")
                            ->orWhere('email', 'like', "%{$trimmed}%");
                    });
            });
        }

        $logs = $query->paginate(25)->withQueryString();

        return Inertia::render('superadmin/audit/index', [
            'logs' => $logs,
            'filters' => [
                'category' => in_array($category, ['all', 'venue', 'security'], true) ? $category : 'all',
                'search' => $search ?? '',
            ],
        ]);
    }
}
