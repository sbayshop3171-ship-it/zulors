<?php

namespace App\Services\Media\Publication;

use App\Models\MediaPublicationItem;
use App\Services\Media\Cloudflare\R2DirectUploadService;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class PublicationMediaProcessor
{
    public function __construct(private readonly R2DirectUploadService $r2)
    {
    }

    /**
     * Complete a publication by validating R2 metadata and copying the object
     * inside R2. User media bytes never enter the queue worker or VPS disk.
     */
    public function process(MediaPublicationItem $item): array
    {
        $publication = $item->publication;
        $kind = $publication?->kind;
        if (! in_array($kind, ['post', 'story', 'chat'], true) || ! in_array($item->type, ['image', 'video', 'audio', 'document'], true)) {
            throw new RuntimeException('Unsupported publication media kind or type.');
        }
        if ($kind === 'story' && ! in_array($item->type, ['image', 'video'], true)) {
            throw new RuntimeException('Stories support photo and video attachments only.');
        }

        $upload = $item->upload ?? [];
        $sourceDisk = $upload['disk'] ?? null;
        $sourcePath = $upload['path'] ?? null;
        $declaredSize = (int) $item->size;
        $maxBytes = match ($item->type) {
            'image' => (int) config('media.publications.image_max_bytes', 20 * 1024 * 1024),
            'video' => (int) config('media.uploads.video.max_bytes', 8 * 1024 * 1024 * 1024),
            'audio' => 1024 * (int) config($kind === 'chat' ? 'chat.validation.message.media.max' : 'post.validation.audio.max', 24576),
            'document' => 1024 * (int) config($kind === 'chat' ? 'chat.validation.message.media.max' : 'post.validation.document.max', 24576),
        };

        if (! is_string($sourceDisk) || $sourceDisk === '' || ! is_string($sourcePath) || $sourcePath === '') {
            throw new RuntimeException('Publication media storage descriptor is incomplete.');
        }
        if ($declaredSize < 1 || $declaredSize > $maxBytes) {
            throw new RuntimeException('Publication media exceeds the upload size limit or is empty.');
        }
        if ((int) Storage::disk($sourceDisk)->size($sourcePath) !== $declaredSize) {
            throw new RuntimeException('Uploaded media size does not match the declared size.');
        }

        $mime = strtolower(trim((string) $item->mime));
        $allowedMime = match ($item->type) {
            'image' => in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true),
            'video' => in_array($mime, ['video/mp4', 'video/quicktime', 'video/webm', 'video/avi', 'video/x-msvideo', 'video/mpeg', 'video/x-m4v', 'video/3gpp', 'video/3gpp2', 'video/x-matroska'], true),
            'audio' => in_array($mime, ['audio/aac', 'audio/mp4', 'audio/mpeg', 'audio/mp3', 'audio/ogg', 'audio/webm', 'audio/wav', 'audio/x-m4a', 'audio/x-wav', 'video/webm'], true),
            'document' => in_array($mime, ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'text/plain', 'application/zip', 'application/x-zip-compressed', 'application/x-rar-compressed', 'application/vnd.rar'], true),
        };
        if (! $allowedMime) {
            throw new RuntimeException('Unsupported publication media type.');
        }

        $extension = strtolower(pathinfo((string) $item->name, PATHINFO_EXTENSION) ?: ($item->type === 'image' ? 'jpg' : 'mp4'));
        if (! preg_match('/\A[a-z0-9]{2,8}\z/', $extension)) {
            $extension = $item->type === 'image' ? 'jpg' : 'mp4';
        }

        $prefix = $this->finalPrefix($publication->getKey(), $item->getKey(), $item->generation);
        $output = $this->r2->publishUploadedObject($sourcePath, $prefix, $extension, $mime, $sourceDisk);
        $metadata = [
            'provider' => 'r2',
            'temp_disk' => $sourceDisk,
            'temp_path' => $sourcePath,
            'final_disk' => $output['disk'],
            'upload_state' => 'uploaded',
            'upload_progress' => 100,
            'upload_completed_at' => now()->toIso8601String(),
            'processing_state' => 'processed',
            'processing_progress' => 100,
            'processing_mode' => 'original_direct',
            'background_processing_state' => 'skipped',
            'background_processing_progress' => 100,
            'original_size' => $declaredSize,
            'optimized_size' => null,
            'optimization_ratio' => null,
        ];

        $duration = (float) data_get($item->metadata, 'duration_seconds', data_get($item->metadata, 'seconds', 0));
        $width = (int) data_get($item->metadata, 'width', data_get($item->metadata, 'dimensions.width', 0));
        $height = (int) data_get($item->metadata, 'height', data_get($item->metadata, 'dimensions.height', 0));
        if ($duration > 0) {
            $metadata['duration'] = parse_duration((int) floor($duration));
            $metadata['duration_seconds'] = $duration;
        }
        if ($width > 0 && $height > 0) {
            $metadata['dimensions'] = ['width' => $width, 'height' => $height];
            $metadata['aspect_ratio'] = round($width / $height, 6);
            $metadata['is_portrait'] = $width < $height;
        }

        return [
            'source_path' => $output['path'],
            'disk' => $output['disk'],
            'extension' => $extension,
            'mime' => $mime,
            'size' => $output['size'],
            'metadata' => $metadata,
        ];
    }

    private function finalPrefix(mixed $publicationId, mixed $itemId, mixed $generation): string
    {
        foreach ([$publicationId, $itemId, $generation] as $segment) {
            if (! is_scalar($segment) || ! preg_match('/\A[a-zA-Z0-9_-]+\z/', (string) $segment)) {
                throw new RuntimeException('Invalid publication media output identity.');
            }
        }

        return "uploads/publications/{$publicationId}/{$itemId}/{$generation}";
    }
}
