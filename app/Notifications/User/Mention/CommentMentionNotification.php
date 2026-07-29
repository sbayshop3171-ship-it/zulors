<?php

namespace App\Notifications\User\Mention;

use App\Models\Comment;
use Illuminate\Bus\Queueable;
use App\Constants\Notifications;
use App\Notifications\Traits\HasUserActor;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Notifications\Channels\WebPushChannel;
use App\Notifications\Traits\BaseNotification;
use Illuminate\Notifications\Messages\MailMessage;

class CommentMentionNotification extends Notification implements ShouldQueue
{
    use Queueable,
        BaseNotification,
        HasUserActor;

    public array $actorData;
    private Comment $commentData;
    private string $commentContent;

    public $notificationType = Notifications::COMMENT_MENTIONED;

    public function __construct(Comment $commentData, string $commentContent)
    {
        $this->actorData = $this->getUserActor();
        $this->commentData = $commentData;
        $this->commentContent = $commentContent;
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
        return (new MailMessage())->subject(__('notifications.subjects.comment_mentioned', locale: $notifiable->language))->view($this->notificationViewPath, [
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

    protected function getDestinationLink(): string
	{
		return url("/publication/{$this->commentData->postHashId}");
	}

    public function toDatabase(): array
    {
        return $this->getData();
    }

    private function getData()
    {
        return [
            'message_group' => 'user',
            'message_key' => 'comment_mentioned',
            'message_params' => [],
            'entity' => [
                'id' => $this->commentData->id,
                'content' => $this->cutContent($this->commentContent),
                'post_hash_id' => $this->commentData->postHashId
            ],
            'actor' => $this->actorData
        ];
    }
}
