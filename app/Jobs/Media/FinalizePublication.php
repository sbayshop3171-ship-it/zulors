<?php

namespace App\Jobs\Media;

use App\Services\Media\Publication\PublicationFinalizer;
use App\Services\Media\Publication\PublicationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class FinalizePublication implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 60;

    public function __construct(public string $publicationId)
    {
        $this->onConnection(config('media.queue_connection'));
        $this->onQueue('default');
    }

    public function backoff(): array { return [30, 60, 120]; }

    public function handle(PublicationFinalizer $finalizer): void
    {
        $finalizer->finalize($this->publicationId);
    }

    public function failed(?Throwable $exception): void
    {
        app(PublicationService::class)->locked($this->publicationId, function ($publication) {
            if(! $publication->terminal()) $publication->update(['status' => 'failed', 'error' => 'Publishing failed. Please retry.']);
        });
    }
}
