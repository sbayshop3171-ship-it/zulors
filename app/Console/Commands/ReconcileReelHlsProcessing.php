<?php

namespace App\Console\Commands;

use App\Enums\Media\MediaStatus;
use App\Enums\Media\MediaType;
use App\Jobs\User\Timeline\GeneratePostVideoHls;
use App\Models\Media;
use App\Services\Media\ReelsHlsRollout;
use Illuminate\Console\Command;

class ReconcileReelHlsProcessing extends Command
{
    protected $signature = 'media:reconcile-reel-hls {--stale-after= : Override stale processing age in minutes} {--retry-failed : Requeue terminally failed HLS processing}';

    protected $description = 'Requeue stale or explicitly selected failed post-video HLS jobs.';

    public function handle(ReelsHlsRollout $rollout): int
    {
        if (! $rollout->processingEnabled()) {
            $this->info('Reels HLS processing is disabled.');

            return self::SUCCESS;
        }

        $staleAfterMinutes = max(15, (int) ($this->option('stale-after') ?: config('media.reels_hls.stale_after_minutes', 120)));
        $cutoff = now()->subMinutes($staleAfterMinutes)->toIso8601String();
        $query = Media::query()
            ->where('type', MediaType::VIDEO->value)
            ->where('status', MediaStatus::PROCESSED->value)
            ->where('metadata->provider', 'r2');

        $query->where(function ($states) use ($cutoff): void {
            $states->where(function ($stale) use ($cutoff): void {
                $stale->whereIn('metadata->background_processing_state', ['queued', 'processing'])
                    ->where(function ($timestamp) use ($cutoff): void {
                        $timestamp->where('metadata->background_processing_updated_at', '<=', $cutoff)
                            ->orWhere(function ($missing) use ($cutoff): void {
                                $missing->whereNull('metadata->background_processing_updated_at')
                                    ->where('metadata->background_processing_dispatched_at', '<=', $cutoff);
                            });
                    });
            });

            if ($this->option('retry-failed')) {
                $states->orWhere('metadata->background_processing_state', 'failed');
            }
        });

        $mediaItems = $query->limit(200)->get();

        foreach ($mediaItems as $media) {
            $metadata = $media->metadata ?? [];
            $metadata['background_processing_state'] = 'queued';
            $metadata['background_processing_progress'] = 0;
            $metadata['background_processing_updated_at'] = now()->toIso8601String();
            $metadata['background_processing_dispatched_at'] = now()->toIso8601String();
            $metadata['background_processing_error'] = null;
            $metadata['playback_state'] = 'ready';
            $metadata['playback'] = array_merge((array) ($metadata['playback'] ?? []), [
                'hls' => null,
                'hls_state' => 'queued',
            ]);
            $media->metadata = $metadata;
            $media->save();

            GeneratePostVideoHls::dispatch((int) $media->id);
        }

        $this->info('Requeued '.$mediaItems->count().' reel HLS job(s).');

        return self::SUCCESS;
    }
}
