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
}
