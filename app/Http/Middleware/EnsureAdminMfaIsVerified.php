<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminMfaIsVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! app()->environment('production')) {
            return $next($request);
        }

        $user = $request->user();

        if (! $user || ! $user->isSuperAdmin()) {
            abort(403);
        }

        $routeName = $request->route()?->getName();

        if (! $user->mfa_secret || ! $user->mfa_enabled_at) {
            if ($routeName === 'mfa.setup') {
                return $next($request);
            }

            return redirect()->route('mfa.setup');
        }

        if ($routeName === 'mfa.challenge') {
            return $next($request);
        }

        $verifiedUserId = $request->session()->get(
            'admin_mfa_verified_user_id'
        );

        $verifiedAt = $request->session()->get(
            'admin_mfa_verified_at'
        );

        if (
            ! is_scalar($verifiedUserId)
            || (string) $verifiedUserId !== (string) $user->getKey()
            || ! is_numeric($verifiedAt)
            || (int) $verifiedAt <= 0
            || (int) $verifiedAt > now()->timestamp
        ) {
            $request->session()->forget([
                'admin_mfa_verified_user_id',
                'admin_mfa_verified_at',
            ]);

            return redirect()->route('mfa.challenge');
        }

        return $next($request);
    }
}
