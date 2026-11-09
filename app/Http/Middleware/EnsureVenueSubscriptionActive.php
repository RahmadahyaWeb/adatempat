<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureVenueSubscriptionActive
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Safe read methods are allowed for read-only historical inspection
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }

        $user = $request->user();

        // Superadmin bypasses subscription restriction for operational overrides
        if ($user && $user->isSuperadmin()) {
            return $next($request);
        }

        $venue = $user?->venue;

        if (! $venue || ! $venue->hasActiveSubscription()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'code' => 'SUBSCRIPTION_INACTIVE',
                    'message' => 'Venue subscription is suspended or expired. Please contact administration for renewal.',
                ], 403);
            }

            abort(403, 'Venue subscription is suspended or expired. Please contact administration for renewal.');
        }

        return $next($request);
    }
}
