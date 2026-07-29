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
        if($event->frameData->type->isVideo()) {
            ProcessStoryVideo::dispatch($event->frameData);
        }

        $this->notifyMentionedUsers($event->frameData);
    }

    private function notifyMentionedUsers(StoryFrame $frameData)
    {
        $mentions = $frameData->getMentions();

        if ($mentions) {
            $mentionedUsers = User::active()->excludeSelf()->whereIn('username', $mentions)->get();
            
            $mentionedUsers->each(function($userData) use ($frameData) {        
                $userData->notify(new StoryMentionNotification($frameData));
            });
        }   
    }
}
