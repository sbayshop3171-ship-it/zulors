<?php

use Aws\S3\S3Client;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__).'/vendor/autoload.php';

$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$failures = [];
$expectedBuckets = [
    'r2_temp' => 'zulors-media-temp',
    'r2_final' => 'zulors-media-final',
];
$r2 = config('media.cloudflare.r2', []);
$temp = config('filesystems.disks.r2_temp', []);
$final = config('filesystems.disks.r2_final', []);
$video = config('media.uploads.video', []);

if ((int) ($video['max_duration_seconds'] ?? 0) < 3600) {
    $failures[] = 'video duration limit is below 3600 seconds';
}
if ((int) ($video['max_bytes'] ?? 0) < 8589934592) {
    $failures[] = 'video size limit is below 8 GiB';
}

if (! (bool) ($r2['direct_upload_enabled'] ?? false)) {
    $failures[] = 'direct R2 uploads are disabled';
}
if (($r2['direct_upload_disk'] ?? 'r2_temp') !== 'r2_temp') {
    $failures[] = 'direct uploads are not targeting the temp bucket';
}
if ((bool) ($r2['auto_cors_enabled'] ?? false)) {
    $failures[] = 'automatic bucket CORS changes must remain disabled';
}
if (($final['url'] ?? '') !== 'https://media.zulors.com') {
    $failures[] = 'final media URL is not https://media.zulors.com';
}

$endpoint = rtrim((string) ($temp['endpoint'] ?? ''), '/');
if ($endpoint === '' || $endpoint !== rtrim((string) ($final['endpoint'] ?? ''), '/')) {
    $failures[] = 'temp and final buckets do not share an R2 endpoint';
}

foreach ($expectedBuckets as $diskName => $expectedBucket) {
    $disk = config("filesystems.disks.{$diskName}", []);
    if (! (bool) ($disk['enabled'] ?? false)) {
        $failures[] = "{$diskName} is disabled";
        continue;
    }
    if (($disk['bucket'] ?? '') !== $expectedBucket) {
        $failures[] = "{$diskName} is not set to its production bucket";
        continue;
    }
    if (blank($disk['key'] ?? null) || blank($disk['secret'] ?? null) || $endpoint === '') {
        $failures[] = "{$diskName} credentials or endpoint are missing";
        continue;
    }

    try {
        $client = new S3Client([
            'version' => 'latest',
            'region' => (string) ($disk['region'] ?? 'auto'),
            'endpoint' => $endpoint,
            'use_path_style_endpoint' => (bool) ($disk['use_path_style_endpoint'] ?? true),
            'credentials' => [
                'key' => (string) $disk['key'],
                'secret' => (string) $disk['secret'],
            ],
        ]);
        $client->headBucket(['Bucket' => $expectedBucket]);
    } catch (Throwable $exception) {
        $failures[] = "{$diskName} S3 connection failed (".class_basename($exception).')';
    }
}

try {
    DB::select('select 1');
} catch (Throwable $exception) {
    $failures[] = 'database connection failed ('.class_basename($exception).')';
}

if ($failures !== []) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "Live preflight failed: {$failure}.\n");
    }
    exit(1);
}

fwrite(STDOUT, "Live preflight passed: database and both production R2 buckets are reachable.\n");
