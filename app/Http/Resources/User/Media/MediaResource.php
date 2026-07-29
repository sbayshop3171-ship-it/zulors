<?php

namespace App\Http\Resources\User\Media;

use Exception;
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
                    'is_portrait',
                    'provider',
                    'cloudflare_uid',
                    'upload_state',
                    'upload_url_expires_at',
                    'upload_completed_at',
                    'upload_method',
                    'upload_type',
                    'temp_disk',
                    'final_disk',
                    'temp_path',
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
        if($this->type->isVideo() && ! $this->status->isProcessed() && data_get($this->metadata, 'provider') === 'r2_temp') {
            try {
                return Storage::disk($this->disk)->temporaryUrl(
                    $this->source_path,
                    now()->addMinutes(config('media.cloudflare.r2.temp_preview_expiry_minutes', 30))
                );
            }
            catch(Exception $e) {
                return null;
            }
        }

        if($this->type->isVideo() && ! $this->status->isProcessed() && data_get($this->metadata, 'provider') !== 'cloudflare_stream') {
            return url("/api/post/editor/media/video/preview/{$this->id}");
        }

        return null;
    }
}
