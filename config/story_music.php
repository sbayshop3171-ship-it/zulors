<?php

return [
    'disk' => env('STORY_MUSIC_DISK', 'r2_music'),
    'prefix' => trim(env('STORY_MUSIC_PREFIX', 'music/story-library'), '/'),
    'signed_url_minutes' => (int) env('STORY_MUSIC_SIGNED_URL_MINUTES', 30),
    'max_per_page' => (int) env('STORY_MUSIC_MAX_PER_PAGE', 50),

    'collections' => [
        'for_you',
        'trending',
        'old',
        'professional',
        'saved',
    ],

    'license_types' => [
        'pixabay',
        'mixkit',
        'cc0',
        'cc-by',
        'licensed',
    ],
];
