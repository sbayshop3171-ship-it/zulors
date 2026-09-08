<?php

namespace App\Jobs;

use App\Models\Media;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

class CleanupProcessedMediaSource implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;
    public int $timeout = 120;
    public int $backoff = 120;

    public function __construct(public int $mediaId, public string $disk, public string $path)
    {
        $this->onConnection(config('media.queue_connection'));
        $this->onQueue(config('media.queues.cleanup'));
    }

    public function handle(): void
    {
        $media = Media::find($this->mediaId);
        if(! $media || ! $media->status->isProcessed()
            || ($media->disk === $this->disk && $media->source_path === $this->path)) return;
        if(! Storage::disk($this->disk)->delete($this->path)) {
            throw new \RuntimeException('Could not delete processed media original.');
        }
    }
}
