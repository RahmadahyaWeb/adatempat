<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Mail\MagicLinkMail;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class MagicLinkController extends Controller
{
    /**
     * Cache key generator for magic link nonces.
     */
    public static function nonceCacheKey(int|string $userId, string $token): string
    {
        return "magic_link:token:{$userId}:{$token}";
    }

    /**
     * Request and send a magic link email.
     */
    public function send(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'intended' => ['nullable', 'string', 'max:500'],
        ]);

        $email = strtolower($validated['email']);
        $rateLimitKey = 'magic-link:send:'.$request->ip().':'.$email;

        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);

            return response()->json([
                'status' => 'error',
                'message' => 'Too many login attempts. Please try again later.',
            ], 429, ['Retry-After' => (string) $seconds]);
        }

        RateLimiter::hit($rateLimitKey, 900); // 15 minutes window

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => Str::before($email, '@'),
                'role' => UserRole::Player,
            ]
        );

        $token = Str::random(40);
        Cache::put(self::nonceCacheKey($user->id, $token), true, now()->addMinutes(15));

        $safeIntended = $this->resolveSafeDestination($validated['intended'] ?? null);

        $url = URL::temporarySignedRoute(
            'auth.magic-link.verify',
            now()->addMinutes(15),
            [
                'user' => $user->id,
                'token' => $token,
                'intended' => $safeIntended,
            ]
        );

        Mail::to($user->email)->send(new MagicLinkMail($url, $user));

        return response()->json([
            'status' => 'success',
            'message' => 'If this email address is valid, a login link has been sent to your inbox.',
            'cooldown_seconds' => 60,
        ]);
    }

    /**
     * Verify the signed magic link and establish user session.
     */
    public function verify(Request $request, User $user): JsonResponse|RedirectResponse
    {
        if (! $request->hasValidSignature()) {
            $invalidMessage = 'This login link is invalid or has expired. Please request a new one.';

            if ($request->wantsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $invalidMessage,
                ], 403);
            }

            return redirect('/')->with('error', $invalidMessage);
        }

        $token = (string) $request->query('token', '');
        $cacheKey = self::nonceCacheKey($user->id, $token);
        $isValidNonce = Cache::pull($cacheKey);

        if (! $isValidNonce) {
            $consumedMessage = 'This login link has already been used or expired. Please request a new one.';

            if ($request->wantsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $consumedMessage,
                ], 400);
            }

            return redirect('/')->with('error', $consumedMessage);
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        $destination = $this->resolveSafeDestination($request->query('intended'));

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'redirect_url' => $destination,
            ]);
        }

        return redirect()->to($destination);
    }

    /**
     * Resolve a safe relative internal destination to defend against open redirect attacks.
     */
    private function resolveSafeDestination(?string $intended): string
    {
        if ($intended !== null && str_starts_with($intended, '/') && ! str_starts_with($intended, '//') && ! str_contains($intended, '\\')) {
            return $intended;
        }

        return '/dashboard';
    }
}
