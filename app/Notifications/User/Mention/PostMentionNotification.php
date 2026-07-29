<?php

namespace App\Notifications\User\Mention;

use App\Models\Post;
use Illuminate\Bus\Queueable;
use App\Constants\Notifications;
use App\Notifications\Traits\HasUserActor;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Notifications\Channels\WebPushChannel;
use App\Notifications\Traits\BaseNotification;
use App\Notifications\Traits\PostNotification;
use Illuminate\Notifications\Messages\MailMessage;

class PostMentionNotification extends Notification implements ShouldQueue
{
    use Queueable,
        PostNotification,
        BaseNotification,
        HasUserActor;

    public array $actorData;

    public $notificationType = Notifications::POST_MENTIONED;

    private Post $postData;

    public function __construct(Post $postData)
    {
        $this->actorData = $this->getUserActor();
        $this->postData = $postData;
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
        return (new MailMessage())->subject(__('notifications.subjects.post_mentioned', locale: $notifiable->language))->view($this->notificationViewPath, [
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
        return [
            'message_group' => 'user',
            'message_key' => 'post_mentioned',
            'message_params' => [],
            'entity' => [
                'id' => $this->postData->id,
                'hash_id' => $this->postData->hashId,
                'preview_lqip_base64' => $this->getPostPreviewLQIPBase64(),
                'content' => $this->cutContent($this->postData->content)
            ],
            'actor' => $this->actorData
        ];
    }

    protected function getDestinationLink(): string
	{
		return url("/publication/{$this->postData->hashId}");
	}
}
