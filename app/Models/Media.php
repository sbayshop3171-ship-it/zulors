<?php
namespace App\Models;

use App\MediaApi\Giphy\Giphy;
use App\Enums\Media\MediaType;
use App\Enums\Media\MediaStatus;
use App\Enums\Media\MediaVisibility;
use App\Events\Media\MediaCreatedEvent;
use App\Events\Media\MediaDeletedEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    protected $dispatchesEvents = [
        'created' => MediaCreatedEvent::class,
        'deleted' => MediaDeletedEvent::class,
    ];

    public $guarded = [];

    public static $snakeAttributes = false;

    protected $attributes = [
        'metadata' => '[]'
    ];

    public $casts = [
        'metadata' => 'array',
        'type' => MediaType::class,
        'status' => MediaStatus::class,
        'visibility' => MediaVisibility::class
    ];

    public function mediaable()
    {
        return $this->morphTo('mediaable', 'mediaable_type', 'mediaable_id', 'id');
    }

    public function message()
    {
        return $this->belongsTo(Message::class, 'mediaable_id', 'id');
    }

    public function post()
    {
        return $this->belongsTo(Post::class, 'mediaable_id', 'id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'mediaable_id', 'id');
    }

    public function storyFrame()
    {
        return $this->belongsTo(StoryFrame::class, 'mediaable_id', 'id');
    }

    public function getSourceUrlAttribute()
    {
        if ($this->disk == Giphy::getDisk()) {
            return $this->source_path;
        }

        if($this->disk == 'cloudflare_stream') {
            if($this->type->isVideo() && ! $this->status->isProcessed()) {
                return null;
            }

            return data_get($this->metadata, 'playback.hls') ?: $this->buildCloudflareStreamUrl('manifest/video.m3u8');
        }

        if($this->type->isDocument()) {
            return route('downloads.document.index', ['mediaId' => $this->id]);
        }
        else if($this->type->isAudio() || $this->type->isVideo()) {
            if(! $this->status->isProcessed()) {
                return null;
            }

            if($this->type->isVideo() && $this->usesTemporaryR2Source()) {
                return $this->temporarySourceUrl() ?: storage_url($this->source_path, $this->disk);
            }
        }

        return storage_url($this->source_path, $this->disk);
    }

    public function getThumbnailUrlAttribute()
    {
        if($this->disk == 'cloudflare_stream' || $this->thumbnail_disk == 'cloudflare_stream') {
            return data_get($this->metadata, 'playback.thumbnail') ?: $this->buildCloudflareStreamUrl('thumbnails/thumbnail.jpg');
        }

        if(empty($this->thumbnail_path)) {
            return null;
        }

        return storage_url($this->thumbnail_path, $this->thumbnail_disk);
    }

    private function buildCloudflareStreamUrl(string $path): ?string
    {
        if(empty($this->source_path)) {
            return null;
        }

        return "https://videodelivery.net/{$this->source_path}/{$path}";
    }

    private function usesTemporaryR2Source(): bool
    {
        $metadata = $this->metadata ?? [];

        return in_array(data_get($metadata, 'provider'), ['r2_temp', 'r2_direct'], true)
            && $this->disk === (string) data_get($metadata, 'temp_disk', config('media.cloudflare.r2.temp_disk', 'r2_temp'))
            && blank(data_get($metadata, 'processed_at'));
    }

    private function temporarySourceUrl(): ?string
    {
        try {
            return Storage::disk($this->disk)->temporaryUrl(
                $this->source_path,
                now()->addMinutes(config('media.cloudflare.r2.temp_preview_expiry_minutes', 30))
            );
        } catch (\Throwable) {
            return null;
        }
    }
}
