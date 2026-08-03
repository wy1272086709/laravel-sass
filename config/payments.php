<?php

return [
    'default' => env('PAYMENT_PROVIDER', 'mock'),

    'providers' => [
        'mock' => [
            'webhook_secret' => env('MOCK_PAYMENT_WEBHOOK_SECRET', 'local-mock-webhook-secret'),
        ],
    ],
];
