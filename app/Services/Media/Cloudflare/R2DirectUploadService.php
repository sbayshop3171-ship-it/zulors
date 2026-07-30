<?php

namespace App\Services\Media\Cloudflare;

use Exception;
use Aws\S3\S3Client;
use Illuminate\Support\Str;
use App\Constants\Filesystem as MediaFilesystem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class R2DirectUploadService
{
    public function isConfigured(): bool
    {
        $tempDisk = $this->tempDisk();
        $finalDisk = $this->finalDisk();

        return (bool) config('media.cloudflare.r2.direct_upload_enabled')
            && (bool) config("filesystems.disks.{$tempDisk}.enabled", false)
            && (bool) config("filesystems.disks.{$finalDisk}.enabled", false)
            && filled(config("filesystems.disks.{$tempDisk}.bucket"))
            && filled(config("filesystems.disks.{$finalDisk}.bucket"))
            && filled(config("filesystems.disks.{$tempDisk}.endpoint"))
            && filled(config("filesystems.disks.{$tempDisk}.key"))
            && filled(config("filesystems.disks.{$tempDisk}.secret"));
    }

    public function createVideoUpload(array $fileData = []): array
    {
        if(! $this->isConfigured()) {
            throw new Exception('Cloudflare R2 direct upload is not configured.');
        }

        $this->ensureCorsConfiguration();

        $mime = (string) ($fileData['mime'] ?? 'video/mp4');
        $extension = $this->cleanExtension((string) ($fileData['extension'] ?? 'mp4'));
        $path = $this->makeTemporaryPath($extension);
        $expiresAt = now()->addMinutes($this->expiryMinutes());
        $size = max(0, (int) ($fileData['size'] ?? 0));

        if($this->shouldUseMultipart($size)) {
            return $this->createMultipartVideoUpload($path, $mime, $size, $expiresAt);
        }

        $upload = Storage::disk($this->tempDisk())->temporaryUploadUrl($path, $expiresAt, [
            'ContentType' => $mime,
        ]);

        return [
            'provider' => 'r2_temp',
            'uid' => $path,
            'path' => $path,
            'disk' => $this->tempDisk(),
            'final_disk' => $this->finalDisk(),
            'upload_url' => $upload['url'],
            'upload_method' => 'PUT',
            'upload_type' => 'raw',
            'upload_headers' => $this->normalizeUploadHeaders($upload['headers'] ?? []),
            'upload_concurrency' => $this->uploadConcurrency(),
            'upload_stall_timeout_ms' => $this->uploadStallTimeoutMs(),
            'raw_fallback_max_bytes' => $this->rawFallbackMaxBytes(),
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }

    public function publishUploadedVideo(string $tempPath, string $extension = 'mp4', string $contentType = 'video/mp4'): array
    {
        if(! $this->isConfigured()) {
            throw new Exception('Cloudflare R2 direct upload is not configured.');
        }

        if(blank($tempPath)) {
            throw new Exception('Invalid direct upload object path.');
        }

        $extension = $this->cleanExtension($extension ?: (string) pathinfo($tempPath, PATHINFO_EXTENSION));
        $finalPath = $this->makeFinalVideoPath($extension);
        $finalDisk = $this->finalDisk();
        $finalClient = $this->s3Client($finalDisk);

        $copyOptions = [
            'Bucket' => $this->bucket($finalDisk),
            'Key' => $finalPath,
            'CopySource' => $this->copySource($this->bucket($this->tempDisk()), $tempPath),
            'ContentType' => $contentType ?: 'video/mp4',
            'MetadataDirective' => 'REPLACE',
        ];

        if($cacheControl = config('media.cache.control')) {
            $copyOptions['CacheControl'] = $cacheControl;
        }

        $finalClient->copyObject($copyOptions);

        $finalObject = $finalClient->headObject([
            'Bucket' => $this->bucket($finalDisk),
            'Key' => $finalPath,
        ]);

        return [
            'disk' => $finalDisk,
            'video_path' => $finalPath,
            'video_size' => (int) $finalObject->get('ContentLength'),
        ];
    }

    public function completeMultipartUpload(string $path, string $uploadId, array $parts): void
    {
        if(! $this->isConfigured()) {
            throw new Exception('Cloudflare R2 direct upload is not configured.');
        }

        $normalizedParts = collect($parts)
            ->map(function(array $part) {
                return [
                    'PartNumber' => (int) ($part['part_number'] ?? $part['PartNumber'] ?? 0),
                    'ETag' => (string) ($part['etag'] ?? $part['ETag'] ?? ''),
                ];
            })
            ->filter(function(array $part) {
                return $part['PartNumber'] > 0 && filled($part['ETag']);
            })
            ->sortBy('PartNumber')
            ->values()
            ->all();

        if(empty($normalizedParts)) {
            throw new Exception('No multipart upload parts were provided.');
        }

        $this->s3Client($this->tempDisk())->completeMultipartUpload([
            'Bucket' => $this->bucket($this->tempDisk()),
            'Key' => $path,
            'UploadId' => $uploadId,
            'MultipartUpload' => [
                'Parts' => $normalizedParts,
            ],
        ]);
    }

    public function uploadRawObject(string $path, mixed $body, string $contentType = 'video/mp4'): void
    {
        if(! $this->isConfigured()) {
            throw new Exception('Cloudflare R2 direct upload is not configured.');
        }

        if(blank($path)) {
            throw new Exception('Invalid direct upload object path.');
        }

        $this->s3Client($this->tempDisk())->putObject([
            'Bucket' => $this->bucket($this->tempDisk()),
            'Key' => $path,
            'Body' => $body,
            'ContentType' => $contentType,
        ]);
    }

    public function uploadMultipartPart(string $path, string $uploadId, int $partNumber, mixed $body): string
    {
        if(! $this->isConfigured()) {
            throw new Exception('Cloudflare R2 direct upload is not configured.');
        }

        if(blank($path) || blank($uploadId) || $partNumber < 1 || $partNumber > 10000) {
            throw new Exception('Invalid multipart upload part request.');
        }

        $result = $this->s3Client($this->tempDisk())->uploadPart([
            'Bucket' => $this->bucket($this->tempDisk()),
            'Key' => $path,
            'UploadId' => $uploadId,
            'PartNumber' => $partNumber,
            'Body' => $body,
        ]);

        return (string) $result->get('ETag');
    }

    public function abortMultipartUpload(string $path, string $uploadId): void
    {
        if(! $this->isConfigured() || blank($path) || blank($uploadId)) {
            return;
        }

        $this->s3Client($this->tempDisk())->abortMultipartUpload([
            'Bucket' => $this->bucket($this->tempDisk()),
            'Key' => $path,
            'UploadId' => $uploadId,
        ]);
    }

    public function uploaded(string $path): bool
    {
        if(! $this->isConfigured()) {
            return false;
        }

        return Storage::disk($this->tempDisk())->exists($path);
    }

    public function tempDisk(): string
    {
        return (string) config('media.cloudflare.r2.temp_disk', 'r2_temp');
    }

    public function finalDisk(): string
    {
        return (string) config('media.cloudflare.r2.final_disk', 'r2_final');
    }

    private function makeTemporaryPath(string $extension): string
    {
        $prefix = trim((string) config('media.cloudflare.r2.temp_prefix', 'tmp/direct/videos'), '/');
        $userId = auth_check() ? me()->id : 'guest';

        return "{$prefix}/{$userId}/".Str::uuid().".{$extension}";
    }

    private function ensureCorsConfiguration(): void
    {
        if(! (bool) config('media.cloudflare.r2.auto_cors_enabled', true)) {
            return;
        }

        $origins = $this->corsOrigins();
        $cacheKey = 'r2-direct-upload-cors:'.sha1($this->tempDisk().'|'.$this->bucket($this->tempDisk()).'|'.implode(',', $origins));

        if(Cache::get($cacheKey)) {
            return;
        }

        try {
            $this->s3Client($this->tempDisk())->putBucketCors([
                'Bucket' => $this->bucket($this->tempDisk()),
                'CORSConfiguration' => [
                    'CORSRules' => [
                        [
                            'AllowedHeaders' => ['*'],
                            'AllowedMethods' => ['PUT', 'POST', 'GET', 'HEAD'],
                            'AllowedOrigins' => $origins,
                            'ExposeHeaders' => ['ETag'],
                            'MaxAgeSeconds' => 3600,
                        ],
                    ],
                ],
            ]);

            Cache::put($cacheKey, true, now()->addHours(12));
        }
        catch(\Throwable $e) {
            $failureCacheKey = "{$cacheKey}:failed";

            if(! Cache::get($failureCacheKey)) {
                Log::warning('Unable to auto-configure R2 direct upload CORS. Browser upload may fall back through the app server.', [
                    'bucket' => $this->bucket($this->tempDisk()),
                    'origins' => $origins,
                    'error' => $e->getMessage(),
                ]);

                Cache::put($failureCacheKey, true, now()->addMinutes(15));
            }
        }
    }

    private function corsOrigins(): array
    {
        $configuredOrigins = collect(explode(',', (string) config('media.cloudflare.r2.cors_origins', '')))
            ->map(fn(string $origin) => $this->normalizeCorsOrigin($origin))
            ->filter()
            ->values()
            ->all();

        $appOrigin = $this->normalizeCorsOrigin((string) config('app.url'));
        $requestOrigin = null;

        if(app()->bound('request')) {
            $requestOrigin = $this->normalizeCorsOrigin((string) request()->headers->get('origin'));
        }

        $origins = array_values(array_unique(array_filter(array_merge($configuredOrigins, [
            $appOrigin,
            $requestOrigin,
        ]))));

        return empty($origins) ? ['*'] : $origins;
    }

    private function normalizeCorsOrigin(?string $origin): ?string
    {
        $origin = trim((string) $origin);

        if(blank($origin)) {
            return null;
        }

        if($origin === '*') {
            return '*';
        }

        if(! str_starts_with($origin, 'http://') && ! str_starts_with($origin, 'https://')) {
            return null;
        }

        $parts = parse_url($origin);

        if(empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }

        $normalizedOrigin = "{$parts['scheme']}://{$parts['host']}";

        if(! empty($parts['port'])) {
            $normalizedOrigin .= ":{$parts['port']}";
        }

        return $normalizedOrigin;
    }

    private function makeFinalVideoPath(string $extension): string
    {
        return MediaFilesystem::mediaNamespace('posts/videos').'/'.Str::uuid().".{$extension}";
    }

    private function copySource(string $bucket, string $path): string
    {
        return "{$bucket}/".str_replace('%2F', '/', rawurlencode($path));
    }

    private function cleanExtension(string $extension): string
    {
        $extension = strtolower(trim($extension));

        if(! preg_match('/^[a-z0-9]{2,8}$/', $extension)) {
            return 'mp4';
        }

        return $extension;
    }

    private function expiryMinutes(): int
    {
        return max(5, (int) config('media.cloudflare.r2.direct_upload_expiry_minutes', 30));
    }

    private function createMultipartVideoUpload(string $path, string $mime, int $size, $expiresAt): array
    {
        $client = $this->s3Client($this->tempDisk());
        $bucket = $this->bucket($this->tempDisk());
        $partSize = $this->multipartPartSize();
        $partCount = (int) ceil($size / $partSize);

        if($partCount < 1 || $partCount > 10000) {
            throw new Exception('Video file is too large for multipart upload.');
        }

        $createdUpload = $client->createMultipartUpload([
            'Bucket' => $bucket,
            'Key' => $path,
            'ContentType' => $mime,
        ]);

        $uploadId = (string) $createdUpload->get('UploadId');

        $parts = [];

        for($partNumber = 1; $partNumber <= $partCount; $partNumber++) {
            $command = $client->getCommand('UploadPart', [
                'Bucket' => $bucket,
                'Key' => $path,
                'UploadId' => $uploadId,
                'PartNumber' => $partNumber,
            ]);

            $parts[] = [
                'part_number' => $partNumber,
                'start' => ($partNumber - 1) * $partSize,
                'end' => min($size, $partNumber * $partSize),
                'upload_url' => (string) $client->createPresignedRequest($command, $expiresAt)->getUri(),
                'upload_method' => 'PUT',
                'upload_headers' => [],
            ];
        }

        return [
            'provider' => 'r2_temp',
            'uid' => $path,
            'path' => $path,
            'disk' => $this->tempDisk(),
            'final_disk' => $this->finalDisk(),
            'upload_url' => null,
            'upload_method' => 'PUT',
            'upload_type' => 'multipart',
            'upload_headers' => [],
            'upload_id' => $uploadId,
            'part_size' => $partSize,
            'parts' => $parts,
            'upload_concurrency' => $this->uploadConcurrency(),
            'upload_stall_timeout_ms' => $this->uploadStallTimeoutMs(),
            'raw_fallback_max_bytes' => $this->rawFallbackMaxBytes(),
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }

    private function shouldUseMultipart(int $size): bool
    {
        return $size >= $this->multipartThreshold();
    }

    private function multipartThreshold(): int
    {
        return max(5, (int) config('media.cloudflare.r2.multipart_threshold_mb', 64)) * 1024 * 1024;
    }

    private function multipartPartSize(): int
    {
        return max(5, (int) config('media.cloudflare.r2.multipart_part_size_mb', 64)) * 1024 * 1024;
    }

    private function uploadConcurrency(): int
    {
        return max(1, min(6, (int) config('media.cloudflare.r2.upload_concurrency', 4)));
    }

    private function uploadStallTimeoutMs(): int
    {
        return max(15, (int) config('media.cloudflare.r2.upload_stall_timeout_seconds', 45)) * 1000;
    }

    private function rawFallbackMaxBytes(): int
    {
        return max(5, (int) config('media.cloudflare.r2.raw_fallback_max_mb', 8)) * 1024 * 1024;
    }

    private function s3Client(string $disk): S3Client
    {
        $config = config("filesystems.disks.{$disk}");

        return new S3Client([
            'version' => 'latest',
            'region' => (string) ($config['region'] ?? 'auto'),
            'endpoint' => (string) $config['endpoint'],
            'use_path_style_endpoint' => (bool) ($config['use_path_style_endpoint'] ?? true),
            'credentials' => [
                'key' => (string) $config['key'],
                'secret' => (string) $config['secret'],
            ],
        ]);
    }

    private function bucket(string $disk): string
    {
        return (string) config("filesystems.disks.{$disk}.bucket");
    }

    private function normalizeUploadHeaders(array $headers): array
    {
        return collect($headers)
            ->reject(function(mixed $value, string $key) {
                return strtolower($key) === 'host';
            })
            ->mapWithKeys(function(mixed $value, string $key) {
                return [$key => is_array($value) ? implode(', ', $value) : (string) $value];
            })
            ->all();
    }
}
