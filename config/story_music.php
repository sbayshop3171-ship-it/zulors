<?php

return [
    'disk' => env('STORY_MUSIC_DISK', 'r2_music'),
    'prefix' => trim(env('STORY_MUSIC_PREFIX', 'music/story-library'), '/'),
    'signed_url_minutes' => (int) env('STORY_MUSIC_SIGNED_URL_MINUTES', 30),
    'max_per_page' => (int) env('STORY_MUSIC_MAX_PER_PAGE', 50),

    'original_audio' => [
        'enabled' => env('STORY_MUSIC_ORIGINAL_AUDIO_ENABLED', true),
        'require_consent' => env('STORY_MUSIC_ORIGINAL_AUDIO_REQUIRE_CONSENT', true),
        'auto_publish' => env('STORY_MUSIC_ORIGINAL_AUDIO_AUTO_PUBLISH', true),
        'min_duration_seconds' => (int) env('STORY_MUSIC_ORIGINAL_AUDIO_MIN_SECONDS', 3),
        'max_duration_seconds' => (int) env('STORY_MUSIC_ORIGINAL_AUDIO_MAX_SECONDS', 180),
        'bitrate' => (int) env('STORY_MUSIC_ORIGINAL_AUDIO_BITRATE', 96),
        'license_url' => env('STORY_MUSIC_ORIGINAL_AUDIO_LICENSE_URL', env('APP_URL') . '/terms'),
    ],

    'collections' => [
        'for_you',
        'trending',
        'new',
        'old',
        'professional',
        'original_audio',
        'saved',
    ],

    'license_types' => [
        'pixabay',
        'mixkit',
        'cc0',
        'cc-by',
        'licensed',
        'ugc-original',
    ],
];
