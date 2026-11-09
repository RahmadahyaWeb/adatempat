<?php

declare(strict_types=1);

use App\Enums\SportType;

test('SportType enum contains expected sports', function () {
    expect(SportType::Badminton->value)->toBe('badminton')
        ->and(SportType::Padel->value)->toBe('padel')
        ->and(SportType::Futsal->value)->toBe('futsal')
        ->and(SportType::Tennis->value)->toBe('tennis')
        ->and(SportType::MiniSoccer->value)->toBe('mini_soccer');
});

test('SportType enum has expected values list', function () {
    $values = array_column(SportType::cases(), 'value');

    expect($values)->toBe([
        'badminton',
        'padel',
        'futsal',
        'tennis',
        'mini_soccer',
    ]);
});
