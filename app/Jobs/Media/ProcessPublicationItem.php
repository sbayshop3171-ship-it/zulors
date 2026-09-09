<?php

namespace App\Jobs\Media;

use App\Models\MediaPublicationItem;
use App\Services\Media\Publication\PublicationMediaProcessor;
use App\Services\Media\Publication\PublicationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessPublicationItem implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 5;
    public int $timeout;
    public int $uniqueFor;

    public function __construct(public string $itemId, public int $generation)
    {
        $this->onConnection(config('media.queue_connection'));
        $item = MediaPublicationItem::find($itemId);
        $this->onQueue(config($item?->type === 'video' ? 'media.queues.video' : 'media.queues.image'));
        $this->timeout = (int) config('media.publications.timeout', 21600);
        $this->uniqueFor = $this->timeout + 300;
    }

    public function uniqueId(): string { return $this->itemId.':'.$this->generation; }

    public function backoff(): array { return [30, 60, 120, 300]; }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('publication-item:'.$this->itemId))->shared()->releaseAfter(30)->expireAfter($this->timeout + 60)];
    }

    public function handle(PublicationService $service, PublicationMediaProcessor $processor): void
    {
        $item = MediaPublicationItem::with('publication')->find($this->itemId);
        if(! $item || $item->generation !== $this->generation || $item->publication->terminal()) return;
        if($item->status === 'processed') {
            FinalizePublication::dispatch($item->publication_id);
            return;
        }
        if(! in_array($item->status, ['uploaded', 'processing'], true)) return;
        $start = $service->locked($item->publication_id, function ($publication) use ($item) {
            if($publication->terminal()) return false;
            $item->update(['status' => 'processing']);
            $publication->update(['status' => 'processing']);
            return true;
        });
        if(! $start) return;
        $output = $processor->process($item->fresh('publication'));
        $accepted = $service->locked($item->publication_id, function ($publication) use ($item, $output) {
            $current = $publication->items()->whereKey($item->id)->first();
            if(! $current || $current->generation !== $this->generation || $publication->terminal()) return false;
            $current->update(['output' => $output, 'status' => 'processed', 'progress' => 100]);
            FinalizePublication::dispatch($publication->id)->afterCommit();
            CleanupPublication::dispatch($publication->id)->afterCommit();
            return true;
        });
        if(! $accepted) {
            // Cancellation may win while FFmpeg is running. Never attach the late result.
            MediaPublicationItem::whereKey($item->id)->update(['outputs_deleted_at' => null]);
            CleanupPublication::dispatch($item->publication_id);
            if(! Storage::disk($output['disk'])->deleteDirectory("uploads/publications/{$item->publication_id}/{$item->id}/{$this->generation}")) {
                throw new \RuntimeException('Late media cleanup is pending.');
            }
        }
    }

    public function failed(?Throwable $exception): void
    {
        $item = MediaPublicationItem::find($this->itemId);
        if(! $item) return;
        app(PublicationService::class)->locked($item->publication_id, function ($publication) use ($item) {
            if($publication->terminal()) return;
            $current = $item->fresh();
            if($current->generation !== $this->generation || $current->status === 'processed') return;
            $current->update(['status' => 'failed']);
            $publication->update(['status' => 'failed', 'error' => 'Media processing failed. Retry or remove this publication.']);
        });
    }
}
