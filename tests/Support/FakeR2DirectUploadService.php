<?php

namespace Tests\Support;

use App\Services\Media\Cloudflare\R2DirectUploadService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FakeR2DirectUploadService extends R2DirectUploadService
{
    public function isConfigured(): bool
    {
        return true;
    }

    public function createVideoUpload(array $fileData = []): array
    {
        $path = 'tmp/direct/videos/' . Str::uuid() . '.mp4';
        $partSize = 64 * 1024 * 1024;
        $parts = [];
        for($offset = 0; $offset < $fileData['size']; $offset += $partSize) {
            $parts[] = ['part_number' => count($parts) + 1, 'start' => $offset,
                'end' => min($fileData['size'], $offset + $partSize), 'upload_url' => 'https://r2.example.test/part'];
        }
        return [
            'provider' => 'r2_direct', 'uid' => $path, 'path' => $path,
            'disk' => 'r2_temp', 'upload_disk' => 'r2_temp', 'final_disk' => 'r2_final',
            'upload_url' => null, 'upload_method' => 'PUT', 'upload_type' => 'multipart',
            'upload_headers' => [], 'upload_id' => 'test-upload', 'part_size' => $partSize,
            'parts' => $parts, 'upload_concurrency' => 3, 'expires_at' => now()->addHour()->toIso8601String(),
        ];
    }

    public function completeMultipartUpload(string $path, string $uploadId, array $parts, ?string $disk = null, int $expectedParts = 0): void
    {
        if(count($parts) !== $expectedParts) throw new \RuntimeException('Missing video parts.');
    }

    public function publishUploadedVideo(string $tempPath, string $extension = 'mp4', string $contentType = 'video/mp4', ?string $sourceDisk = null): array
    {
        $published = $this->publishUploadedObject($tempPath, 'uploads/posts/videos', $extension, $contentType, $sourceDisk);
        return ['disk' => $published['disk'], 'video_path' => $published['path'], 'video_size' => $published['size']];
    }

    public function publishUploadedObject(string $tempPath, string $targetPrefix, string $extension, string $contentType, ?string $sourceDisk = null): array
    {
        $sourceDisk = $sourceDisk ?: 'r2_temp';
        $targetDisk = 'r2_final';
        $path = trim($targetPrefix, '/') . '/' . sha1($sourceDisk . '|' . $tempPath) . '.' . preg_replace('/[^a-zA-Z0-9]/', '', $extension);
        $source = Storage::disk($sourceDisk);
        $target = Storage::disk($targetDisk);
        if(! $target->exists($path)) {
            $target->put($path, $source->get($tempPath));
        }
        $size = (int) $target->size($path);
        $source->delete($tempPath);
        return ['disk' => $targetDisk, 'path' => $path, 'size' => $size];
    }
}
