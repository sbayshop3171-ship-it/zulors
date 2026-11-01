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

        $allowedUserIds = array_map('strval', config('media.reels_hls.user_ids', []));

        return in_array('*', $allowedUserIds, true)
            || in_array((string) $user->getKey(), $allowedUserIds, true);
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
