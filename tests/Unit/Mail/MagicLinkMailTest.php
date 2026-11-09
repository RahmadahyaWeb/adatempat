<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Mail\MagicLinkMail;
use App\Models\User;

test('MagicLinkMail renders correct subject, greeting, and signed url', function () {
    $user = new User([
        'name' => 'Budi Santoso',
        'email' => 'budi@example.com',
        'role' => UserRole::Player,
    ]);

    $url = 'https://adatempat.test/auth/magic-link/verify/1?token=sample&signature=xyz';

    $mailable = new MagicLinkMail($url, $user);

    expect($mailable->envelope()->subject)->toBe('Your Login Link - adatempat');

    $rendered = $mailable->render();

    expect($rendered)->toContain('Halo, Budi Santoso!')
        ->toContain('15 menit')
        ->toContain(e($url));
});
