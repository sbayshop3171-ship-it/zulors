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
        if($event->frameData->type->isVideo() && ! $event->frameData->media->first()?->status->isProcessed()) {
            ProcessStoryVideo::dispatch($event->frameData)->onQueue(config('media.queues.video_high'));
        }

        $this->notifyMentionedUsers($event->frameData);
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
