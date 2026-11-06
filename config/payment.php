<?php

use App\Services\Payments\FakePaymentGateway;

return [
    'default' => env('PAYMENT_GATEWAY_DRIVER', 'fake'),

    'drivers' => [
        'fake' => [
            'class' => FakePaymentGateway::class,
        ],
    ],
];
