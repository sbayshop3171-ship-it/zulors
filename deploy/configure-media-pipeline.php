<?php

// Update only rollout settings; preserve existing secrets and the legacy queue name.
require dirname(__DIR__) . '/vendor/autoload.php';
$path = dirname(__DIR__) . '/.env';
$contents = file_get_contents($path);
$existing = Dotenv\Dotenv::parse($contents);
if(($existing['APP_ENV'] ?? '') !== 'production') {
    throw new RuntimeException('Media rollout configuration requires the production release environment.');
}
$currentVersion = (int) ($existing['R2_MEDIA_PIPELINE_VERSION'] ?? 0);
if($currentVersion >= 2) {
    echo "Media rollout configuration v2 already applied.\n";
    exit(0);
}
$settings = [
    'R2_MEDIA_PIPELINE_VERSION' => '2',
    // The direct R2 pipeline supports long originals without sending their
    // bytes through PHP or the VPS. Keep production aligned with the current
    // application policy (8 GiB and 60 minutes).
    'MEDIA_VIDEO_MAX_BYTES' => '8589934592',
    'MEDIA_VIDEO_MAX_DURATION_SECONDS' => '3600',
];
if($currentVersion < 1) {
    $settings = array_merge([
        'R2_DIRECT_UPLOAD_DISK' => 'r2_temp',
        'MEDIA_QUEUE_CONNECTION' => 'redis',
        'MEDIA_VIDEO_SHARED_WORKER' => $existing['MEDIA_VIDEO_SHARED_WORKER'] ?? 'true',
        'MEDIA_FFMPEG_THREADS' => $existing['MEDIA_FFMPEG_THREADS'] ?? '1',
        'MEDIA_VIDEO_MAX_PROCESSES' => $existing['MEDIA_VIDEO_MAX_PROCESSES'] ?? '1',
        'MEDIA_VIDEO_HIGH_QUEUE' => $existing['MEDIA_VIDEO_HIGH_QUEUE'] ?? 'media-video-high',
        'POST_VIDEO_MAX_SIZE' => '1048576',
        'CHAT_MEDIA_MAX_SIZE' => '1048576',
    ], $settings);
}
$lines = preg_split('/\r?\n/', $contents);
foreach($lines as &$line) {
    if(preg_match('/^([A-Z][A-Z0-9_]*)=/', $line, $match) && array_key_exists($match[1], $settings)) {
        $line = $match[1] . '=' . $settings[$match[1]];
        unset($settings[$match[1]]);
    }
}
unset($line);
foreach($settings as $key => $value) $lines[] = $key . '=' . $value;
$updated = rtrim(implode("\n", $lines)) . "\n";
if($updated !== $contents) {
    $temporary = tempnam(dirname($path), '.env.media-');
    chmod($temporary, 0600);
    if(file_put_contents($temporary, $updated) === false || ! rename($temporary, $path)) {
        throw new RuntimeException('Could not save media rollout environment settings.');
    }
}
echo "Media rollout settings configured; secrets and existing queue aliases preserved.\n";
