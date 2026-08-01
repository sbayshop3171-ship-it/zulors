<?php

namespace App\Http\Resources\User\Media;

use Throwable;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Resources\Json\JsonResource;

class MediaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $mediaItem = [
            'id' => $this->id,
            'mediaable_id' => $this->mediaable_id,
            'source_url' => $this->source_url,
            'preview_url' => $this->getPreviewUrl(),
            'extension' => $this->extension,
            'type' => $this->type->value,
            'size' => $this->size,
            'status' => $this->status->value,
            'thumbnail_url' => $this->thumbnail_url,
            'thumbnail_size' => $this->thumbnail_size,
            'lqip_base64' => $this->lqip_base64,
            'metadata' => $this->getMetadata($this->metadata)
        ];

        return $mediaItem;
    }

    private function getMetadata(array $metadata)
    {
        if(is_array($metadata)) {
            if($this->type->isVideo()) {
                $metadata = Arr::only($metadata, [
                    'duration',
                    'duration_seconds',
                    'original_duration_seconds',
                    'clip_start_seconds',
                    'clip_end_seconds',
                    'dimensions',
                    'aspect_ratio',
                    'is_portrait',
                    'provider',
                    'cloudflare_uid',
                    'upload_state',
                    'upload_url_expires_at',
                    'upload_completed_at',
                    'upload_method',
                    'upload_type',
                    'upload_id',
                    'part_size',
                    'parts_count',
                    'upload_progress',
                    'upload_progress_updated_at',
                    'upload_failed_at',
                    'temp_disk',
                    'upload_disk',
                    'final_disk',
                    'temp_path',
                    'processing_progress',
                    'processing_state',
                    'processing_started_at',
                    'processing_updated_at',
                    'processing_dispatched_at',
                    'processing_error',
                    'processing_fallback',
                    'processed_at',
                    'original_size',
                    'optimized_size',
                    'optimization_ratio',
                    'playback',
                    'original_name'
                ]);
            }

            return $metadata;
        }
        
        return [];
    }

    private function getPreviewUrl(): ?string
    {
        if($this->type->isVideo() && ! $this->status->isProcessed() && in_array(data_get($this->metadata, 'provider'), ['r2_temp', 'r2_direct'], true)) {
            try {
                return Storage::disk($this->disk)->temporaryUrl(
                    $this->source_path,
                    now()->addMinutes(config('media.cloudflare.r2.temp_preview_expiry_minutes', 30))
                );
            }
            catch(Throwable $e) {
                return null;
            }
        }

        if($this->type->isVideo() && ! $this->status->isProcessed() && data_get($this->metadata, 'provider') !== 'cloudflare_stream') {
            return url("/api/post/editor/media/video/preview/{$this->id}");
        }

        return null;
    }
}
