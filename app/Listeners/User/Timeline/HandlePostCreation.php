<?php

namespace App\Listeners\User\Timeline;

use App\Models\Post;

use App\Models\User;
use App\Services\Censor\CensorService;
use App\Events\User\Timeline\PostCreatedEvent;
use App\Jobs\User\Timeline\ConvertAndCompressPostAudio;
use App\Jobs\User\Timeline\ConvertAndCompressPostVideo;
use App\Notifications\User\Mention\PostMentionNotification;

class HandlePostCreation
{
    public function handle(PostCreatedEvent $event): void
    {
        if ($event->postData->type->isVideo()) {
            ConvertAndCompressPostVideo::dispatch($event->postData)->onQueue(config('media.queues.video'));
        }

        else if($event->postData->type->isAudio()) {
            ConvertAndCompressPostAudio::dispatch($event->postData)->onQueue(config('media.queues.audio'));
        }

        $this->notifyMentionedUsers($event->postData);

        $this->censorPost($event->postData);
    }

    private function censorPost(Post $postData)
    {
        $censorService = app(CensorService::class);

        $censorService->setUser($postData->user)->censor($postData->content);
    }

    private function notifyMentionedUsers(Post $postData)
    {
        $mentions = $postData->getMentions();

        if ($mentions) {
            $mentionedUsers = User::active()->excludeSelf()->whereIn('username', $mentions)->get();
            
            $mentionedUsers->each(function($userData) use ($postData) {        
                $userData->notify(new PostMentionNotification($postData));
            });
        }   
    }
}
