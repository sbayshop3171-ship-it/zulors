<?php

namespace App\Notifications\User\Post;

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

class PostCommentedNotification extends Notification implements ShouldQueue
{
    use Queueable,
        PostNotification,
        BaseNotification,
        HasUserActor;

    public $notificationType = Notifications::POST_COMMENTED;

    private Post $postData;
    public array $actorData;
    private string $commentContent;

    public function __construct(Post $postData, string $commentContent)
    {
        $this->postData = $postData;
        $this->actorData = $this->getUserActor();
        $this->commentContent = $commentContent;
    }

    public function via(object $notifiable): array
    {
        $channels = [];

		if($notifiable->pushNotificationSettings->comments) {
			if($this->isPushEnabled()) {
				array_push($channels, WebPushChannel::class);
			}

			if($this->isBroadcastEnabled()) {
				array_push($channels, 'broadcast');
			}

			array_push($channels, 'database');
		}

		if($notifiable->emailNotificationSettings->comments) {
			if($this->isEmailEnabled()) {
				array_push($channels, 'mail');
			}
		}

		return $channels;
    }

    public function toPush(object $notifiable): array
    {
        return [
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())->subject(__('notifications.subjects.post_commented', locale: $notifiable->language))->view($this->notificationViewPath, [
            'notifiable' => $notifiable,
            'data' => $this->getData(),
            'notificationType' => $this->notificationType,
            'destinationLink' => $this->getDestinationLink(),
            'locale' => $notifiable->language
        ]);
    }

    public function toDatabase(): array
    {
        return $this->getData();
    }

    private function getData()
    {
        return [
            'message_group' => 'post',
            'message_key' => 'post_commented',
            'message_params' => [],
            'entity' => [
                'id' => $this->postData->id,
                'hash_id' => $this->postData->hashId,
                'content' => $this->cutContent($this->commentContent),
                'preview_lqip_base64' => $this->getPostPreviewLQIPBase64()
            ],
            'actor' => $this->actorData
        ];
    }

    protected function getDestinationLink(): string
	{
		return url("/publication/{$this->postData->hashId}");
	}
}
