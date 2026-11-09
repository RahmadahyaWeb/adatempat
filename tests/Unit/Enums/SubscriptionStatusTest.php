<?php

declare(strict_types=1);

use App\Enums\SubscriptionStatus;

test('SubscriptionStatus enum contains expected values', function () {
    expect(SubscriptionStatus::Trial->value)->toBe('trial')
        ->and(SubscriptionStatus::Active->value)->toBe('active')
        ->and(SubscriptionStatus::Suspended->value)->toBe('suspended')
        ->and(SubscriptionStatus::Expired->value)->toBe('expired');
});

test('SubscriptionStatus canMutate determines active mutation eligibility', function () {
    expect(SubscriptionStatus::Trial->canMutate())->toBeTrue()
        ->and(SubscriptionStatus::Active->canMutate())->toBeTrue()
        ->and(SubscriptionStatus::Suspended->canMutate())->toBeFalse()
        ->and(SubscriptionStatus::Expired->canMutate())->toBeFalse();
});
