<?php

declare(strict_types=1);

use App\Enums\PaymentStatus;

test('PaymentStatus enum contains expected string values matching glossary', function () {
    expect(PaymentStatus::Pending->value)->toBe('pending')
        ->and(PaymentStatus::Paid->value)->toBe('paid')
        ->and(PaymentStatus::Expired->value)->toBe('expired')
        ->and(PaymentStatus::Rescheduled->value)->toBe('rescheduled')
        ->and(PaymentStatus::Completed->value)->toBe('completed')
        ->and(PaymentStatus::Cancelled->value)->toBe('cancelled');
});

test('PaymentStatus values list returns all defined statuses', function () {
    expect(PaymentStatus::values())->toBe([
        'pending',
        'paid',
        'expired',
        'rescheduled',
        'completed',
        'cancelled',
    ]);
});
