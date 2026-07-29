<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],
    'giphy' => [
        'api_key' => env('GIPHY_API_KEY'),
    ],
    'vonage' => [
        'api_key' => env('VONAGE_API_KEY'),
        'api_secret' => env('VONAGE_API_SECRET'),
        'from_number' => env('VONAGE_FROM_NUMBER'),
    ],
    'smsaero' => [
        'login' => env('SMSAERO_LOGIN'),
        'api_key' => env('SMSAERO_API_KEY'),
        'sender_name' => env('SMSAERO_SENDER_NAME'),
        'channel' => env('SMSAERO_CHANNEL'),
    ],
    'ipinfo' => [
        'token' => env('IPINFO_TOKEN'),
    ],
    'translation' => [
        'api_url' => env('TRANSLATION_SERVICE_API_URL'),
        'api_key' => env('TRANSLATION_SERVICE_API_KEY'),
        'service' => env('TRANSLATION_SERVICE'),
        'logo' => env('TRANSLATION_SERVICE_LOGO'),
        'name' => env('TRANSLATION_SERVICE_NAME'),
        'url' => env('TRANSLATION_SERVICE_URL'),
    ]
];
