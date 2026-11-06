<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperadminTwoFactorEnforced
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->role === UserRole::Superadmin) {
            if (! $user->hasEnabledTwoFactorAuthentication()) {
                if ($request->expectsJson()) {
                    abort(403, 'Autentikasi dua faktor (2FA) wajib diaktifkan untuk akun superadmin.');
                }

                return redirect()->route('security.edit')->with('error', 'Akun Superadmin wajib mengaktifkan Autentikasi Dua Faktor (2FA) sebelum dapat mengakses panel admin.');
            }
        }

        return $next($request);
    }
}
