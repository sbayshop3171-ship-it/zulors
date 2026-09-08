<?php

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

// Probe private temp storage without enabling public uploads or creating DB rows.
chdir(dirname(__DIR__));
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$origins = array_values(array_unique(array_slice($argv, 1) ?: [rtrim(config('app.url'), '/')]));
foreach($origins as $origin) {
    $parts = parse_url($origin);
    if(! in_array($parts['scheme'] ?? '', ['http', 'https'], true) || empty($parts['host'])
        || isset($parts['user']) || isset($parts['fragment'])
        || ! empty($parts['path']) || isset($parts['query'])) {
        throw new InvalidArgumentException('Provide origins without paths, queries, or credentials.');
    }
}
$diskName = config('media.cloudflare.r2.temp_disk');
$finalName = config('media.cloudflare.r2.final_disk');
$bucket = config("filesystems.disks.{$diskName}.bucket");
if(! $bucket || $diskName === $finalName || $bucket === config("filesystems.disks.{$finalName}.bucket")) {
    throw new RuntimeException('Distinct configured temp and final buckets are required.');
}
$client = Storage::disk($diskName)->getClient();
$http = new Client(['connect_timeout' => 10, 'timeout' => 30, 'http_errors' => false]);
$report = ['checks' => [], 'cleanup_errors' => []];
$passed = true;
foreach(['single', 'multipart'] as $mode) {
    $key = 'tmp/direct/cors-check/' . Str::uuid() . '.bin';
    $uploadId = null;
    $objectAttempted = false;
    try {
        $arguments = ['Bucket' => $bucket, 'Key' => $key];
        if($mode === 'multipart') {
            $uploadId = $client->createMultipartUpload($arguments + ['ContentType' => 'application/octet-stream'])->get('UploadId');
            $command = $client->getCommand('UploadPart', $arguments + ['UploadId' => $uploadId, 'PartNumber' => 1]);
        }
        else $command = $client->getCommand('PutObject', $arguments + ['ContentType' => 'application/octet-stream']);
        $url = (string) $client->createPresignedRequest($command, '+5 minutes')->getUri();
        foreach($origins as $origin) {
            $preflight = $http->request('OPTIONS', $url, ['headers' => [
                'Origin' => $origin, 'Access-Control-Request-Method' => 'PUT',
                'Access-Control-Request-Headers' => 'content-type',
            ]]);
            $check = ['mode' => $mode, 'origin' => $origin, 'preflight_status' => $preflight->getStatusCode()];
            $allowedMethods = array_map('trim', explode(',', strtoupper($preflight->getHeaderLine('Access-Control-Allow-Methods'))));
            $allowedHeaders = array_map('trim', explode(',', strtolower($preflight->getHeaderLine('Access-Control-Allow-Headers'))));
            $ok = $preflight->getStatusCode() >= 200 && $preflight->getStatusCode() < 300
                && in_array($preflight->getHeaderLine('Access-Control-Allow-Origin'), [$origin, '*'], true)
                && in_array('PUT', $allowedMethods, true)
                && (in_array('content-type', $allowedHeaders, true) || in_array('*', $allowedHeaders, true));
            if($ok) {
                $objectAttempted = $mode === 'single';
                $response = $http->request('PUT', $url, ['headers' => [
                    'Origin' => $origin, 'Content-Type' => 'application/octet-stream',
                ], 'body' => 'Zulors disposable CORS probe.']);
                $exposed = array_map('trim', explode(',', strtolower($response->getHeaderLine('Access-Control-Expose-Headers'))));
                $check['put_status'] = $response->getStatusCode();
                $check['etag_visible'] = $response->getHeaderLine('ETag') !== ''
                    && (in_array('etag', $exposed, true) || in_array('*', $exposed, true));
                $ok = $response->getStatusCode() >= 200 && $response->getStatusCode() < 300
                    && in_array($response->getHeaderLine('Access-Control-Allow-Origin'), [$origin, '*'], true)
                    && $check['etag_visible'];
            }
            $check['passed'] = $ok;
            $report['checks'][] = $check;
            $passed = $passed && $ok;
        }
    }
    catch (Throwable $e) {
        // Exception messages can contain signed URLs; do not print them.
        $report['checks'][] = ['mode' => $mode, 'passed' => false, 'error_type' => get_class($e)];
        $passed = false;
    }
    finally {
        try {
            if($uploadId) $client->abortMultipartUpload(['Bucket' => $bucket, 'Key' => $key, 'UploadId' => $uploadId]);
            if($objectAttempted) $client->deleteObject(['Bucket' => $bucket, 'Key' => $key]);
        }
        catch (Throwable $e) {
            $report['cleanup_errors'][] = ['key' => $key, 'error_type' => get_class($e)];
            $passed = false;
        }
    }
}
$report['passed'] = $passed;
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
exit($passed ? 0 : 1);
