<?php

namespace App\Console\Commands\Story;

use App\Models\StoryFrame;
use Carbon\Carbon;
use App\Enums\Media\MediaStatus;
use App\Enums\Story\StoryStatus;
use Illuminate\Console\Command;
use App\Actions\Story\DeleteStoryFrameAction;
use App\Notifications\User\Important\StoryExpiredNotification;

class DeleteExpiredStories extends Command
{
    protected $signature = 'story:clear';

    protected $description = 'This command deletes all expired stories from the database.';

    public function handle()
    {
        $failedGraceMinutes = max(1, (int) config('story.failed_processing_cleanup_grace_minutes', 10));

        $deletedCount = 0;

        StoryFrame::query()
            ->where(function($query) use ($failedGraceMinutes) {
                $query->where('expires_at', '<=', now())
                    ->orWhere('created_at', '<', now()->subDays(3))
                    ->orWhere(function($failedQuery) use ($failedGraceMinutes) {
                        $failedQuery->where('status', StoryStatus::PROCESSING)
                            ->where('created_at', '<', now()->subMinutes($failedGraceMinutes))
                            ->whereHas('media', function($mediaQuery) {
                                $mediaQuery->where(function($failedMediaQuery) {
                                    $failedMediaQuery->where('status', MediaStatus::FAILED)
                                        ->orWhere('metadata->processing_state', 'failed');
                                });
                            });
                    });
            })
            ->with(['story.user', 'media'])
            ->orderBy('id')
            ->chunkById(100, function($storyFrames) use (&$deletedCount) {
                $storyFrames->each(function(StoryFrame $storyFrame) use (&$deletedCount) {
                    if($this->shouldNotifyStoryExpired($storyFrame)) {
                        $storyFrame->story?->user?->notify(new StoryExpiredNotification($storyFrame));
                    }

                    (new DeleteStoryFrameAction($storyFrame))->execute();
                    $deletedCount += 1;
                });
            });

        story_log('Expired stories deleted successfully.', [
            'count' => $deletedCount
        ]);
    }

    private function shouldNotifyStoryExpired(StoryFrame $storyFrame): bool
    {
        $expiresAt = $storyFrame->getRawOriginal('expires_at');

        return ($storyFrame->status === StoryStatus::ACTIVE) && ! empty($expiresAt) && Carbon::parse($expiresAt)->lte(now());
    }
}
