<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;

test('user model casts role to UserRole enum and defaults to player', function () {
    $user = new User([
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ]);

    expect($user->role)->toBeNull();

    $user->role = UserRole::Player;
    expect($user->role)->toBe(UserRole::Player);
});

test('user model helper methods identify roles correctly', function () {
    $player = new User(['role' => UserRole::Player]);
    $operator = new User(['role' => UserRole::VenueOperator]);
    $superadmin = new User(['role' => UserRole::Superadmin]);

    expect($player->isPlayer())->toBeTrue()
        ->and($player->isVenueOperator())->toBeFalse()
        ->and($player->isSuperadmin())->toBeFalse();

    expect($operator->isVenueOperator())->toBeTrue()
        ->and($operator->isPlayer())->toBeFalse()
        ->and($operator->isSuperadmin())->toBeFalse();

    expect($superadmin->isSuperadmin())->toBeTrue()
        ->and($superadmin->isPlayer())->toBeFalse()
        ->and($superadmin->isVenueOperator())->toBeFalse();
});
