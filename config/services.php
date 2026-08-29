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
    'google' => [
        'native_client_ids' => array_values(array_filter(array_map('trim', explode(',', env(
            'GOOGLE_NATIVE_CLIENT_IDS',
            '505126705219-c4alnqlmvgio1oh1p1qedjj2unj6s6m3.apps.googleusercontent.com'
        ))))),
    ],
    'translation' => [
        'api_url' => env('TRANSLATION_SERVICE_API_URL'),
        'api_key' => env('TRANSLATION_SERVICE_API_KEY'),
        'service' => env('TRANSLATION_SERVICE'),
        'logo' => env('TRANSLATION_SERVICE_LOGO'),
        'name' => env('TRANSLATION_SERVICE_NAME'),
        'url' => env('TRANSLATION_SERVICE_URL'),
    ],
    'calls' => [
        'media_provider' => env('CALL_MEDIA_PROVIDER', 'auto'),
        'stun_urls' => array_filter(array_map('trim', explode(',', env('CALL_STUN_URLS', 'stun:stun.l.google.com:19302')))),
        'turn_urls' => array_filter(array_map('trim', explode(',', env('CALL_TURN_URLS', '')))),
        'turn_username' => env('CALL_TURN_USERNAME'),
        'turn_credential' => env('CALL_TURN_CREDENTIAL'),
        'turn_secret' => env('CALL_TURN_SECRET'),
        'turn_ttl_seconds' => (int) env('CALL_TURN_TTL_SECONDS', 3600),
        'agora' => [
            'app_id' => env('AGORA_APP_ID'),
            'app_certificate' => env('AGORA_APP_CERTIFICATE'),
            'token_ttl_seconds' => (int) env('AGORA_TOKEN_TTL_SECONDS', 3600),
            'area_code' => env('AGORA_AREA_CODE', 'GLOBAL'),
            'excluded_area' => env('AGORA_EXCLUDED_AREA'),
            'audio_encoder_profile' => env('AGORA_AUDIO_ENCODER_PROFILE', 'speech_low_quality'),
            'audio_bitrate_kbps' => (int) env('AGORA_AUDIO_BITRATE_KBPS', 18),
            'audio_bitrate_floor_kbps' => (int) env('AGORA_AUDIO_BITRATE_FLOOR_KBPS', 16),
            'audio_sample_rate' => (int) env('AGORA_AUDIO_SAMPLE_RATE', 16000),
            'audio_route_preset' => env('AGORA_AUDIO_ROUTE_PRESET', 'earpiece'),
            'reconnect_grace_seconds' => (int) env('AGORA_RECONNECT_GRACE_SECONDS', 60),
        ],
    ],
];
