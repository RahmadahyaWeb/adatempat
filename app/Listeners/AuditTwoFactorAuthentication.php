<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\Audit\AuditService;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;

class AuditTwoFactorAuthentication
{
    /**
     * Handle two-factor authentication events.
     */
    public function handle(TwoFactorAuthenticationConfirmed|TwoFactorAuthenticationDisabled $event): void
    {
        /** @var User $user */
        $user = $event->user;

        if ($event instanceof TwoFactorAuthenticationConfirmed) {
            AuditService::log(
                action: 'user.2fa_enabled',
                auditable: $user,
                payload: [
                    'email' => $user->email,
                    'confirmed_at' => now()->toIso8601String(),
                ],
                actor: $user,
            );

            return;
        }

        AuditService::log(
            action: 'user.2fa_disabled',
            auditable: $user,
            payload: [
                'email' => $user->email,
                'disabled_at' => now()->toIso8601String(),
            ],
            actor: $user,
        );
    }
}
