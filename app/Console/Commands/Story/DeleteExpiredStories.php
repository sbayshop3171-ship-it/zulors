<?php

namespace App\Console\Commands\Story;

use App\Models\StoryFrame;
use App\Enums\Media\MediaStatus;
use App\Enums\Story\StoryStatus;
use Illuminate\Console\Command;
use App\Actions\Story\DeleteStoryFrameAction;

class DeleteExpiredStories extends Command
{
    protected $signature = 'story:clear';

    protected $description = 'This command deletes all expired stories from the database.';

    public function handle()
    {
        $failedGraceMinutes = max(1, (int) config('story.failed_processing_cleanup_grace_minutes', 10));

        $expiredStories = StoryFrame::query()
            ->where(function($query) use ($failedGraceMinutes) {
                $query->where('expires_at', '<', now())
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
            ->take(100)
            ->get();

        $expiredStories->each(function ($storyFrame) {
            (new DeleteStoryFrameAction($storyFrame))->execute();
        });

        story_log('Expired stories deleted successfully.', [
            'count' => $expiredStories->count()
        ]);
    }
}
