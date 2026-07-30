<?php

return [
    'queues' => [
        'video' => env('MEDIA_VIDEO_QUEUE', 'media-video'),
        'audio' => env('MEDIA_AUDIO_QUEUE', 'media-audio'),
    ],

    'cache' => [
        'control' => env('MEDIA_CACHE_CONTROL', 'public, max-age=31536000, immutable'),
    ],

    'images' => [
        'max_width' => (int) env('MEDIA_IMAGE_MAX_WIDTH', 2048),
        'max_height' => (int) env('MEDIA_IMAGE_MAX_HEIGHT', 2048),
    ],

    'cloudflare' => [
        'r2' => [
            'direct_upload_enabled' => env('R2_DIRECT_UPLOAD_ENABLED', false),
            'temp_disk' => env('R2_TEMP_DISK', 'r2_temp'),
            'final_disk' => env('R2_FINAL_DISK', 'r2_final'),
            'temp_prefix' => trim(env('R2_TEMP_PREFIX', 'tmp/direct/videos'), '/'),
            // Upload directly to the public/final bucket so completion never copies a large file.
            'direct_upload_disk' => env('R2_DIRECT_UPLOAD_DISK', 'r2_final'),
            'direct_upload_prefix' => trim(env('R2_DIRECT_UPLOAD_PREFIX', 'uploads/posts/videos'), '/'),
            // Long uploads must keep their signed part URLs valid for the whole transfer.
            'direct_upload_expiry_minutes' => (int) env('R2_DIRECT_UPLOAD_EXPIRY_MINUTES', 720),
            'multipart_threshold_mb' => (int) env('R2_DIRECT_UPLOAD_MULTIPART_THRESHOLD_MB', 16),
            'multipart_part_size_mb' => (int) env('R2_DIRECT_UPLOAD_MULTIPART_PART_SIZE_MB', 16),
            'upload_concurrency' => (int) env('R2_DIRECT_UPLOAD_CONCURRENCY', 4),
            'upload_stall_timeout_seconds' => (int) env('R2_DIRECT_UPLOAD_STALL_TIMEOUT_SECONDS', 300),
            'raw_fallback_max_mb' => (int) env('R2_DIRECT_UPLOAD_RAW_FALLBACK_MAX_MB', 8),
            'part_fallback_max_mb' => (int) env(
                'R2_DIRECT_UPLOAD_PART_FALLBACK_MAX_MB',
                env('R2_DIRECT_UPLOAD_MULTIPART_PART_SIZE_MB', 16)
            ),
            'temp_preview_expiry_minutes' => (int) env('R2_TEMP_PREVIEW_EXPIRY_MINUTES', 30),
            'auto_cors_enabled' => env('R2_DIRECT_UPLOAD_AUTO_CORS_ENABLED', true),
            'cors_origins' => env('R2_DIRECT_UPLOAD_CORS_ORIGINS'),
        ],

        'stream' => [
            'enabled' => env('CLOUDFLARE_STREAM_ENABLED', false),
            'account_id' => env('CLOUDFLARE_ACCOUNT_ID'),
            'api_token' => env('CLOUDFLARE_STREAM_API_TOKEN'),
            'webhook_secret' => env('CLOUDFLARE_STREAM_WEBHOOK_SECRET'),
            'customer_subdomain' => env('CLOUDFLARE_STREAM_CUSTOMER_SUBDOMAIN'),
            'require_signed_urls' => env('CLOUDFLARE_STREAM_REQUIRE_SIGNED_URLS', false),
            'direct_upload_expiry_minutes' => env('CLOUDFLARE_STREAM_DIRECT_UPLOAD_EXPIRY_MINUTES', 60),
            'max_duration_seconds' => env('CLOUDFLARE_STREAM_MAX_DURATION_SECONDS', 36000),
        ],
    ],
];
