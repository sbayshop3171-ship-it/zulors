<?php

namespace App\Services\Media;

use App\Models\User;
use App\Models\Media;
use App\Jobs\User\Timeline\GeneratePostVideoHls;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ReelsHlsRollout
{
    public function processingEnabled(): bool
    {
        return (bool) config('media.reels_hls.enabled', false);
    }

    public function playbackEnabledFor(?User $user): bool
    {
        if (! $this->processingEnabled() || ! $user) {
            return false;
        }

        $allowedUserIds = config('media.reels_hls.user_ids', []);

        return in_array((string) $user->getKey(), array_map('strval', $allowedUserIds), true);
    }

    public function dispatch(Media $media): void
    {
        if (! $this->processingEnabled()) {
            return;
        }

        try {
            GeneratePostVideoHls::dispatch((int) $media->id)->afterCommit();
        } catch (Throwable $exception) {
            Log::warning('Unable to enqueue R2 video HLS processing; stale-job recovery will retry it.', [
                'exception' => $exception::class,
            ]);
        }
    }
}
