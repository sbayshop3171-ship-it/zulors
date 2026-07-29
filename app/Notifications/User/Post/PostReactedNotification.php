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

class PostReactedNotification extends Notification implements ShouldQueue
{
    use Queueable,
        PostNotification,
        BaseNotification,
        HasUserActor;

    public $notificationType = Notifications::POST_REACTED;

    private Post $postData;
    public array $actorData;
    private string $reactionUnifiedId;

    public function __construct(Post $postData, string $reactionUnifiedId)
    {
        $this->postData = $postData;
        $this->actorData = $this->getUserActor();
        $this->reactionUnifiedId = $reactionUnifiedId;
    }

    public function via(object $notifiable): array
    {
        $channels = [];

		if($notifiable->pushNotificationSettings->reactions) {
			if($this->isPushEnabled()) {
				array_push($channels, WebPushChannel::class);
			}

			if($this->isBroadcastEnabled()) {
				array_push($channels, 'broadcast');
			}

			array_push($channels, 'database');
		}

		if($notifiable->emailNotificationSettings->reactions) {
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
        return (new MailMessage())->subject(__('notifications.subjects.post_reacted', locale: $notifiable->language))->view($this->notificationViewPath, [
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
            'message_key' => 'post_reacted',
            'message_params' => [],
            'metadata' => [
                'reaction_unified_id' => $this->reactionUnifiedId
            ],
            'entity' => [
                'id' => $this->postData->id,
                'hash_id' => $this->postData->hashId,
                'preview_lqip_base64' => $this->getPostPreviewLQIPBase64(),
                'content' => empty($this->postData->content) ? null : $this->cutContent($this->postData->content)
            ],
            'actor' => $this->actorData
        ];
    }

	protected function getDestinationLink(): string
	{
		return url("/publication/{$this->postData->hashId}");
	}
}
