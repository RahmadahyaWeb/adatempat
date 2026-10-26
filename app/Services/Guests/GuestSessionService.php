<?php

namespace App\Services\Guests;

use App\Models\GuestSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GuestSessionService
{
    /**
     * The raw token is returned once and must be kept in a secure, HttpOnly cookie.
     * Only its SHA-256 hash is persisted.
     *
     * @return array{session:GuestSession,token:string}
     */
    public function create(string $nickname, ?string $ipAddress = null): array
    {
        $nickname = trim($nickname);

        if ($nickname === '' || mb_strlen($nickname) > 80) {
            throw ValidationException::withMessages([
                'nickname' => 'Nickname wajib diisi dan maksimal 80 karakter.',
            ]);
        }

        $token = Str::random(64);
        $now = now('UTC');

        $session = GuestSession::query()->create([
            'session_token_hash' => hash('sha256', $token),
            'nickname' => $nickname,
            'converted_to_user_id' => null,
            'ip_hash' => hash('sha256', (string) $ipAddress),
            'expires_at' => $now->copy()->addDays(30),
            'last_active_at' => $now,
        ]);

        return ['session' => $session, 'token' => $token];
    }

    public function resolveToken(string $token): ?GuestSession
    {
        if ($token === '' || mb_strlen($token) > 256) {
            return null;
        }

        $session = GuestSession::query()
            ->where('session_token_hash', hash('sha256', $token))
            ->where('expires_at', '>', now('UTC'))
            ->first();

        if (! $session) {
            return null;
        }

        $session->update(['last_active_at' => now('UTC')]);

        return $session->refresh();
    }

    public function convertToUser(string $token, User $user): GuestSession
    {
        abort_unless($user->status === 'active' && $user->email_verified_at !== null, 403);

        return DB::transaction(function () use ($token, $user): GuestSession {
            $session = GuestSession::query()
                ->where('session_token_hash', hash('sha256', $token))
                ->lockForUpdate()
                ->firstOrFail();

            if ($session->expires_at->isPast()) {
                throw ValidationException::withMessages([
                    'guestSession' => 'Sesi tamu sudah kedaluwarsa.',
                ]);
            }

            if ($session->converted_to_user_id !== null && (int) $session->converted_to_user_id !== (int) $user->getKey()) {
                throw ValidationException::withMessages([
                    'guestSession' => 'Sesi tamu sudah ditautkan ke akun lain.',
                ]);
            }

            $session->update([
                'converted_to_user_id' => $user->getKey(),
                'last_active_at' => now('UTC'),
            ]);

            return $session->refresh();
        }, attempts: 3);
    }
}
