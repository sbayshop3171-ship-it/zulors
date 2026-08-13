<?php

return [
    'reverb' => [
        'enabled' => env('VITE_REVERB_CONNECTION_STATUS', env('REVERB_CONNECTION_STATUS', 'off')) === 'on',
        'app_key' => env('VITE_REVERB_APP_KEY') ?: env('REVERB_APP_KEY'),
        'host' => env('VITE_REVERB_HOST') ?: env('REVERB_HOST'),
        'port' => env('VITE_REVERB_PORT') ?: env('REVERB_PORT'),
        'scheme' => env('VITE_REVERB_SCHEME') ?: env('REVERB_SCHEME', 'https'),
    ],
];
