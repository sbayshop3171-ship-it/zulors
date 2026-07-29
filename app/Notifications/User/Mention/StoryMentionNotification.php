<?php

namespace App\Notifications\User\Mention;

use App\Models\StoryFrame;
use Illuminate\Bus\Queueable;
use App\Constants\Notifications;
use App\Notifications\Traits\HasUserActor;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Notifications\Channels\WebPushChannel;
use App\Notifications\Traits\BaseNotification;
use Illuminate\Notifications\Messages\MailMessage;

class StoryMentionNotification extends Notification implements ShouldQueue
{
    use Queueable,
        BaseNotification,
        HasUserActor;

    public array $actorData;

    public $notificationType = Notifications::STORY_MENTIONED;

    private StoryFrame $frameData;

    public function __construct(StoryFrame $frameData)
    {
        $this->actorData = $this->getUserActor();
        $this->frameData = $frameData;
    }

    public function via(object $notifiable): array
    {
        $channels = [];

		if($notifiable->pushNotificationSettings->mentions) {
			if($this->isPushEnabled()) {
				array_push($channels, WebPushChannel::class);
			}

			if($this->isBroadcastEnabled()) {
				array_push($channels, 'broadcast');
			}

			array_push($channels, 'database');
		}

		if($notifiable->emailNotificationSettings->mentions) {
			if($this->isEmailEnabled()) {
				array_push($channels, 'mail');
			}
		}

		return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())->subject(__('notifications.subjects.story_mentioned', locale: $notifiable->language))->view($this->notificationViewPath, [
            'notifiable' => $notifiable,
            'data' => $this->getData(),
            'notificationType' => $this->notificationType,
            'destinationLink' => $this->getDestinationLink(),
            'locale' => $notifiable->language
        ]);
    }

    public function toPush(object $notifiable): array
    {
        return [
        ];
    }

    public function toDatabase(): array
    {
        return $this->getData();
    }

    private function getData()
    {
        $media = $this->frameData->media->first();

        return [
            'message_group' => 'user',
            'message_key' => 'story_mentioned',
            'message_params' => [],
            'entity' => [
                'story_uuid' => $this->frameData->story->story_uuid,
                'content' => $this->cutContent($this->frameData->content),
                'preview_lqip_base64' => $media->lqip_base64
            ],
            'actor' => $this->actorData
        ];
    }

    protected function getDestinationLink(): string
	{
		return url("/stories/{$this->frameData->story->story_uuid}");
	}
}
