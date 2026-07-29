<?php

namespace App\Notifications\User\Post;

use App\Models\Comment;
use Illuminate\Bus\Queueable;
use App\Constants\Notifications;
use App\Notifications\Traits\HasUserActor;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Notifications\Channels\WebPushChannel;
use App\Notifications\Traits\BaseNotification;
use Illuminate\Notifications\Messages\MailMessage;

class CommentReactedNotification extends Notification implements ShouldQueue
{
    use Queueable,
        BaseNotification,
        HasUserActor;

    public $notificationType = Notifications::COMMENT_REACTED;

    private Comment $commentData;
    public array $actorData;
    private string $reactionUnifiedId;

    public function __construct(Comment $commentData, string $reactionUnifiedId)
    {
        $this->commentData = $commentData;
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
        return (new MailMessage())->subject(__('notifications.subjects.comment_reacted', locale: $notifiable->language))->view($this->notificationViewPath, [
            'notifiable' => $notifiable,
            'data' => $this->getData(),
            'notificationType' => $this->notificationType,
            'destinationLink' => $this->getDestinationLink(),
            'locale' => $notifiable->language
        ]);
    }

    private function getData()
    {
        return [
            'message_group' => 'post',
            'message_key' => 'comment_reacted',
            'message_params' => [],
            'metadata' => [
                'reaction_unified_id' => $this->reactionUnifiedId
            ],
            'entity' => [
                'id' => $this->commentData->id,
                'content' => $this->cutContent($this->commentData->content),
                'post_hash_id' => $this->commentData->postHashId
            ],
            'actor' => $this->actorData
        ];
    }

    public function toDatabase(): array
    {
        return $this->getData();
    }

    protected function getDestinationLink(): string
	{
		return url("/publication/{$this->commentData->postHashId}");
	}
}
