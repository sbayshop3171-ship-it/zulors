<?php

namespace App\Services\Media\Cloudflare;

use Exception;
use Illuminate\Support\Str;
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

        $mime = (string) ($fileData['mime'] ?? 'video/mp4');
        $extension = $this->cleanExtension((string) ($fileData['extension'] ?? 'mp4'));
        $path = $this->makeTemporaryPath($extension);
        $expiresAt = now()->addMinutes($this->expiryMinutes());

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
            'expires_at' => $expiresAt->toIso8601String(),
        ];
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
