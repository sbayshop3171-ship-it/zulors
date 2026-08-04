<?php

namespace App\Notifications\User\Important;

use App\Constants\Notifications;
use App\Models\StoryFrame;
use App\Notifications\Traits\BaseNotification;
use App\Notifications\Traits\HasSystemActor;
use Illuminate\Notifications\Notification;

class StoryExpiredNotification extends Notification
{
    use BaseNotification,
        HasSystemActor;

    public array $actorData;

    public $notificationType = Notifications::STORY_EXPIRED;

    public function __construct(private StoryFrame $frameData)
    {
        $this->actorData = $this->getSystemActor();
    }

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if($this->isBroadcastEnabled()) {
            $channels[] = 'broadcast';
        }

        return $channels;
    }

    public function toDatabase(): array
    {
        return [
            'message_group' => 'important',
            'message_key' => 'story_expired',
            'message_params' => [],
            'entity' => [
                'id' => $this->frameData->id,
                'story_uuid' => $this->frameData->story?->story_uuid,
                'content' => $this->cutContent((string) ($this->frameData->content ?? '')),
            ],
            'actor' => $this->actorData,
            'metadata' => [
                'is_viewable' => false,
            ],
        ];
    }
}
