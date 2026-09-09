<?php

namespace App\Listeners\User\Story;

use App\Models\User;
use App\Models\StoryFrame;
use App\Jobs\User\Story\ProcessStoryVideo;
use App\Events\User\Story\StoryCreatedEvent;
use App\Notifications\User\Mention\StoryMentionNotification;

class HandleStoryCreation
{
    public function handle(StoryCreatedEvent $event): void
    {
        $media = $event->frameData->media->first();

        if($event->frameData->type->isVideo() && (! $media?->status->isProcessed() || $this->isInstantR2MediaAwaitingOptimization($media))) {
            ProcessStoryVideo::dispatch($event->frameData)->onQueue(config('media.queues.video_high'));
        }

        $this->notifyMentionedUsers($event->frameData);
    }

    private function isInstantR2MediaAwaitingOptimization($media): bool
    {
        if(empty($media)) {
            return false;
        }

        $metadata = $media->metadata ?? [];

        return $media->status->isProcessed()
            && in_array(data_get($metadata, 'provider'), ['r2_temp', 'r2_direct'], true)
            && data_get($metadata, 'upload_state') === 'uploaded'
            && blank(data_get($metadata, 'processed_at'));
    }

    private function notifyMentionedUsers(StoryFrame $frameData)
    {
        if ($frameData->media->contains(fn ($media) => filled(data_get($media->metadata, 'publication_id')))) {
            return;
        }

        $mentions = $frameData->getMentions();

        if ($mentions) {
            $mentionedUsers = User::active()->excludeSelf()->whereIn('username', $mentions)->get();
            
            $mentionedUsers->each(function($userData) use ($frameData) {        
                $userData->notify(new StoryMentionNotification($frameData));
            });
        }   
    }
}
