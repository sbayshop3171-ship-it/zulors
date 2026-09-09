<?php

namespace App\Jobs\Media;

use App\Models\MediaPublication;
use App\Services\Media\Cloudflare\R2DirectUploadService;
use Aws\S3\Exception\S3Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class CleanupPublication implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;
    public int $timeout = 300;

    public function __construct(public string $publicationId)
    {
        $this->onConnection(config('media.queue_connection'));
        $this->onQueue(config('media.queues.cleanup'));
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('publication-cleanup:'.$this->publicationId))->shared()->releaseAfter(30)->expireAfter(360)];
    }

    public function backoff(): array { return [60, 300, 900, 3600]; }

    public function handle(R2DirectUploadService $r2): void
    {
        $publication = MediaPublication::with('items')->find($this->publicationId);
        if(! $publication) return;
        foreach($publication->items as $item) {
            if($publication->status !== 'cancelled' && $item->status !== 'processed') continue;
            $upload = $item->upload;
            if($upload && ! $item->original_deleted_at) {
                if(($upload['upload_type'] ?? '') === 'multipart') {
                    try {
                        $r2->abortMultipartUpload($upload['path'], $upload['upload_id'], $upload['disk']);
                    } catch(S3Exception $e) {
                        if($e->getAwsErrorCode() !== 'NoSuchUpload') throw $e;
                    }
                }
                if(! Storage::disk($upload['disk'])->delete($upload['path'])) {
                    throw new RuntimeException('Original media cleanup is pending.');
                }
                $item->update(['original_deleted_at' => now()]);
            }
            if($publication->status === 'cancelled' && ! $item->outputs_deleted_at) {
                $disk = $item->output['disk'] ?? $upload['final_disk'] ?? config('media.cloudflare.r2.final_disk');
                if(! Storage::disk($disk)->deleteDirectory("uploads/publications/{$publication->id}/{$item->id}")) {
                    throw new RuntimeException('Cancelled media cleanup is pending.');
                }
                $item->update(['outputs_deleted_at' => now()]);
            }
        }
    }
}
