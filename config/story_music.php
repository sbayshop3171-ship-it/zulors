<?php

return [
    'disk' => env('STORY_MUSIC_DISK', 'r2_music'),
    'prefix' => trim(env('STORY_MUSIC_PREFIX', 'music/story-library'), '/'),
    'signed_url_minutes' => (int) env('STORY_MUSIC_SIGNED_URL_MINUTES', 30),
    'max_per_page' => (int) env('STORY_MUSIC_MAX_PER_PAGE', 50),

    'original_audio' => [
        'enabled' => env('STORY_MUSIC_ORIGINAL_AUDIO_ENABLED', true),
        'require_consent' => env('STORY_MUSIC_ORIGINAL_AUDIO_REQUIRE_CONSENT', false),
        'require_allowed_category' => env('STORY_MUSIC_ORIGINAL_AUDIO_REQUIRE_ALLOWED_CATEGORY', true),
        'auto_publish' => env('STORY_MUSIC_ORIGINAL_AUDIO_AUTO_PUBLISH', true),
        'min_duration_seconds' => (int) env('STORY_MUSIC_ORIGINAL_AUDIO_MIN_SECONDS', 3),
        'max_duration_seconds' => (int) env('STORY_MUSIC_ORIGINAL_AUDIO_MAX_SECONDS', 60),
        'bitrate' => (int) env('STORY_MUSIC_ORIGINAL_AUDIO_BITRATE', 96),
        'expire_after_hours' => (int) env('STORY_MUSIC_ORIGINAL_AUDIO_EXPIRE_AFTER_HOURS', 24),
        'license_url' => env('STORY_MUSIC_ORIGINAL_AUDIO_LICENSE_URL', env('APP_URL') . '/terms'),
        'analysis' => [
            'enabled' => env('STORY_MUSIC_ORIGINAL_AUDIO_ANALYSIS_ENABLED', true),
            'min_score' => (int) env('STORY_MUSIC_ORIGINAL_AUDIO_MIN_SCORE', 55),
            'min_sample_rate' => (int) env('STORY_MUSIC_ORIGINAL_AUDIO_MIN_SAMPLE_RATE', 16000),
            'max_silence_ratio' => (float) env('STORY_MUSIC_ORIGINAL_AUDIO_MAX_SILENCE_RATIO', 0.65),
            'min_mean_volume_db' => (float) env('STORY_MUSIC_ORIGINAL_AUDIO_MIN_MEAN_VOLUME_DB', -45),
            'min_max_volume_db' => (float) env('STORY_MUSIC_ORIGINAL_AUDIO_MIN_MAX_VOLUME_DB', -38),
            'silence_noise_db' => env('STORY_MUSIC_ORIGINAL_AUDIO_SILENCE_NOISE_DB', '-45dB'),
            'ffmpeg_timeout' => (int) env('STORY_MUSIC_ORIGINAL_AUDIO_ANALYSIS_TIMEOUT', 90),
        ],
        'recognition' => [
            'enabled' => env('STORY_MUSIC_RECOGNITION_ENABLED', false),
            'provider' => env('STORY_MUSIC_RECOGNITION_PROVIDER'),
            'min_confidence' => (int) env('STORY_MUSIC_RECOGNITION_MIN_CONFIDENCE', 70),
        ],
        'allowed_categories' => [
            'music_video',
            'reel',
            'story_music_source',
        ],
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
