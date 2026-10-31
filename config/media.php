<?php

return [
    'publications' => [
        'enabled' => (bool) env('MEDIA_PUBLICATIONS_ENABLED', true),
        'allowed_user_ids' => array_filter(explode(',', (string) env('MEDIA_PUBLICATIONS_ALLOWED_USER_IDS', ''))),
        'kinds' => array_filter(explode(',', (string) env('MEDIA_PUBLICATIONS_KINDS', 'post,story,chat'))),
        'per_user_limit' => (int) env('MEDIA_PUBLICATIONS_PER_USER_LIMIT', 2),
        'video_limit' => (int) env('MEDIA_PUBLICATIONS_VIDEO_LIMIT', 20),
        'max_queue_age_seconds' => (int) env(
            'MEDIA_PUBLICATIONS_MAX_QUEUE_AGE_SECONDS',
            env('MEDIA_PUBLICATIONS_MAX_QUEUE_AGE', 900)
        ),
        'image_max_pixels' => (int) env('MEDIA_PUBLICATIONS_IMAGE_MAX_PIXELS', 40000000),
        'image_max_bytes' => (int) env('MEDIA_PUBLICATIONS_IMAGE_MAX_BYTES', 20971520),
        'timeout' => (int) env('MEDIA_PUBLICATIONS_TIMEOUT', 21600),
    ],
    'queue_connection' => env('MEDIA_QUEUE_CONNECTION', 'redis'),
    'processing' => [
        'ffmpeg_threads' => (int) env('MEDIA_FFMPEG_THREADS', 2),
    ],
    'queues' => [
        'video' => env('MEDIA_VIDEO_QUEUE', env('MEDIA_VIDEO_NORMAL_QUEUE', 'media-video-normal')),
        'video_high' => env('MEDIA_VIDEO_HIGH_QUEUE', 'media-video-high'),
        'video_normal' => env('MEDIA_VIDEO_NORMAL_QUEUE', env('MEDIA_VIDEO_QUEUE', 'media-video-normal')),
        'audio' => env('MEDIA_AUDIO_QUEUE', 'media-audio'),
        'image' => env('MEDIA_IMAGE_QUEUE', 'media-image'),
        'cleanup' => env('MEDIA_CLEANUP_QUEUE', 'media-cleanup'),
    ],

    'cache' => [
        'control' => env('MEDIA_CACHE_CONTROL', 'public, max-age=31536000, immutable'),
    ],

    'images' => [
        'max_width' => (int) env('MEDIA_IMAGE_MAX_WIDTH', 2048),
        'max_height' => (int) env('MEDIA_IMAGE_MAX_HEIGHT', 2048),
    ],

    'uploads' => [
        'video' => [
            // Keep video bytes off the Laravel request path for every client.
            'direct_only' => true,
            'max_bytes' => (int) env('MEDIA_VIDEO_MAX_BYTES', 8 * 1024 * 1024 * 1024),
            'max_duration_seconds' => (int) env('MEDIA_VIDEO_MAX_DURATION_SECONDS', 3600),
        ],
    ],

    'cloudflare' => [
        'r2' => [
            'direct_upload_enabled' => env('R2_DIRECT_UPLOAD_ENABLED', false),
            'temp_disk' => env('R2_TEMP_DISK', 'r2_temp'),
            'final_disk' => env('R2_FINAL_DISK', 'r2_final'),
            'temp_prefix' => trim(env('R2_TEMP_PREFIX', 'tmp/direct/media'), '/'),
            // Default to the temp bucket so raw uploads can be compressed before publishing.
            'direct_upload_disk' => env('R2_DIRECT_UPLOAD_DISK', 'r2_temp'),
            'direct_upload_prefix' => trim(env('R2_DIRECT_UPLOAD_PREFIX', 'uploads/posts/videos'), '/'),
            // Long uploads must keep their signed part URLs valid for the whole transfer.
            'direct_upload_expiry_minutes' => (int) env('R2_DIRECT_UPLOAD_EXPIRY_MINUTES', 720),
            'multipart_threshold_mb' => (int) env('R2_DIRECT_UPLOAD_MULTIPART_THRESHOLD_MB', 8),
            'multipart_part_size_mb' => (int) env('R2_DIRECT_UPLOAD_MULTIPART_PART_SIZE_MB', 8),
            'upload_concurrency' => (int) env('R2_DIRECT_UPLOAD_CONCURRENCY', 4),
            'upload_stall_timeout_seconds' => (int) env('R2_DIRECT_UPLOAD_STALL_TIMEOUT_SECONDS', 0),
            // Media bytes must never fall back through the Laravel/VPS request path.
            'raw_fallback_max_mb' => 0,
            'part_fallback_max_mb' => 0,
            'temp_preview_expiry_minutes' => (int) env('R2_TEMP_PREVIEW_EXPIRY_MINUTES', 30),
            // Configure bucket CORS out of band; upload requests must not mutate
            // bucket-wide policy using an Origin supplied by the browser.
            'auto_cors_enabled' => env('R2_DIRECT_UPLOAD_AUTO_CORS_ENABLED', false),
            'cors_origins' => env('R2_DIRECT_UPLOAD_CORS_ORIGINS'),
        ],

        'stream' => [
            // Existing Stream media can keep its historical playback URLs.
            // This project does not create new Stream uploads.
            'webhook_secret' => env('CLOUDFLARE_STREAM_WEBHOOK_SECRET'),
            'customer_subdomain' => env('CLOUDFLARE_STREAM_CUSTOMER_SUBDOMAIN'),
        ],
    ],
];
