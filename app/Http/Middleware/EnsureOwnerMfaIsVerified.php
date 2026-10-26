<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOwnerMfaIsVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! app()->environment('production')) {
            return $next($request);
        }

        $user = $request->user();

        if (! $user || ! $user->isOwner() || $user->status !== 'active') {
            abort(403);
        }

        $routeName = $request->route()?->getName();

        if (! $user->mfa_secret || ! $user->mfa_enabled_at) {
            if ($routeName === 'owner.mfa.setup') {
                return $next($request);
            }

            return redirect()->route('owner.mfa.setup');
        }

        if ($routeName === 'owner.mfa.setup' || $routeName === 'owner.mfa.challenge') {
            return $next($request);
        }

        $verifiedUserId = $request->session()->get('owner_mfa_verified_user_id');
        $verifiedAt = $request->session()->get('owner_mfa_verified_at');
        $now = now()->timestamp;

        if (
            ! is_scalar($verifiedUserId)
            || (string) $verifiedUserId !== (string) $user->getKey()
            || ! is_numeric($verifiedAt)
            || (int) $verifiedAt <= 0
            || (int) $verifiedAt > $now
            || (int) $verifiedAt < $now - 600
        ) {
            $request->session()->forget([
                'owner_mfa_verified_user_id',
                'owner_mfa_verified_at',
            ]);

            return redirect()->route('owner.mfa.challenge');
        }

        return $next($request);
    }
}
