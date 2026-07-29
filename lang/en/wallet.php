<?php

return [
    'validation' => [
        'deposit' => [
            'provider_not_configured' => ':provider is not configured yet. Please add its credentials in the admin panel.',
            'intent_failed' => 'Payment could not be started. Please try another payment method.',
        ],
        'transfer' => [
            'amount' => [
                'can_afford' => 'You do not have enough balance to make a transfer.',
            ],
        ],
    ],
];
