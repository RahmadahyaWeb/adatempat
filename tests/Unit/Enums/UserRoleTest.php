<?php

declare(strict_types=1);

use App\Enums\UserRole;

test('UserRole enum contains expected string values', function () {
    expect(UserRole::Player->value)->toBe('player')
        ->and(UserRole::VenueOperator->value)->toBe('venue_operator')
        ->and(UserRole::Superadmin->value)->toBe('superadmin');
});

test('UserRole enum has expected values list', function () {
    $values = array_column(UserRole::cases(), 'value');

    expect($values)->toBe(['player', 'venue_operator', 'superadmin']);
});
