<?php

namespace App\Jobs\Media;

use App\Models\MediaPublicationItem;
use App\Services\Media\Publication\PublicationMediaProcessor;
use App\Services\Media\Publication\PublicationFinalizer;
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

    public function handle(PublicationService $service, PublicationMediaProcessor $processor, ?PublicationFinalizer $finalizer = null): void
    {
        $finalizer ??= app(PublicationFinalizer::class);
        $item = MediaPublicationItem::with('publication')->find($this->itemId);
        if(! $item || $item->generation !== $this->generation || $item->publication->status === 'cancelled') return;
        if($item->publication->status === 'published' && ! $this->canOptimizePublishedItem($item)) return;
        if($item->status === 'processed') {
            if($item->publication->status !== 'published') {
                FinalizePublication::dispatch($item->publication_id);
            }
            return;
        }
        if(! in_array($item->status, ['uploaded', 'processing'], true)) return;
        $start = $service->locked($item->publication_id, function ($publication) use ($item) {
            if($publication->status === 'cancelled') return false;
            if($publication->status === 'published' && ! $this->canOptimizePublishedItem($item)) return false;
            $item->update(['status' => 'processing']);
            if($publication->status !== 'published') {
                $publication->update(['status' => 'processing']);
            }
            return true;
        });
        if(! $start) return;
        $output = $processor->process($item->fresh('publication'));
        $accepted = $service->locked($item->publication_id, function ($publication) use ($item, $output, $finalizer) {
            $current = $publication->items()->whereKey($item->id)->first();
            if(! $current || $current->generation !== $this->generation || $publication->status === 'cancelled') return false;
            if($publication->status === 'published' && ! $this->canOptimizePublishedItem($current)) return false;
            if($publication->status === 'published') {
                $finalizer->replacePublishedMedia($publication, $current, $output);
            }
            $current->update(['output' => $output, 'status' => 'processed', 'progress' => 100]);
            if($publication->status !== 'published') {
                FinalizePublication::dispatch($publication->id)->afterCommit();
            }
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
            if($publication->status === 'cancelled') return;
            $current = $item->fresh();
            if($current->generation !== $this->generation || $current->status === 'processed') return;
            if($publication->status === 'published' && ! $this->canOptimizePublishedItem($current)) return;
            $current->update(['status' => 'failed']);
            if($publication->status !== 'published') {
                $publication->update(['status' => 'failed', 'error' => 'Media processing failed. Retry or remove this publication.']);
            }
        });
    }

    private function canOptimizePublishedItem(MediaPublicationItem $item): bool
    {
        return $item->type === 'video'
            && $item->publication?->has_video
            && in_array($item->status, ['uploaded', 'processing'], true);
    }
}
