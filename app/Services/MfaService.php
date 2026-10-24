<?php

namespace App\Services;

use PragmaRX\Google2FALaravel\Facade as Google2FA;

class MfaService
{
    public function generateSecret(): string
    {
        return Google2FA::generateSecretKey();
    }

    public function verify(string $secret, string $code): bool
    {
        if (! preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        return Google2FA::verifyKey($secret, $code, 1);
    }

    public function provisioningUri(
        string $email,
        string $secret,
    ): string {
        return Google2FA::getQRCodeUrl(
            config('app.name', 'Adatempat'),
            $email,
            $secret,
        );
    }
}
