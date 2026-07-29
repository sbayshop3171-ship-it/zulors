<?php

namespace App\Console\Commands\Story;

use App\Models\StoryFrame;
use Illuminate\Console\Command;
use App\Actions\Story\DeleteStoryFrameAction;

class DeleteExpiredStories extends Command
{
    protected $signature = 'story:clear';

    protected $description = 'This command deletes all expired stories from the database.';

    public function handle()
    {
        $expiredStories = StoryFrame::where('expires_at', '<', now())
            ->orWhere('created_at', '<', now()->subDays(3))->take(100)->get();

        $expiredStories->each(function ($storyFrame) {
            (new DeleteStoryFrameAction($storyFrame))->execute();
        });

        story_log('Expired stories deleted successfully.', [
            'count' => $expiredStories->count()
        ]);
    }
}
