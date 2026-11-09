<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsVenueOperator
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthenticated.',
                ], 401);
            }

            return redirect()->route('login');
        }

        if ($user->isSuperadmin()) {
            return $next($request);
        }

        if (! $user->isVenueOperator()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Access denied. Venue operator role required.',
                ], 403);
            }

            abort(403, 'Access denied. Venue operator role required.');
        }

        if ($user->venue_id === null) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Access denied. Operator is not assigned to any venue.',
                ], 403);
            }

            abort(403, 'Access denied. Operator is not assigned to any venue.');
        }

        return $next($request);
    }
}
