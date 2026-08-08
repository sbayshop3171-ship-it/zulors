<?php

namespace App\Notifications\User\Story;

use App\Constants\Notifications;
use App\Models\StoryFrame;
use App\Notifications\Channels\WebPushChannel;
use App\Notifications\Traits\BaseNotification;
use App\Notifications\Traits\HasUserActor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StoryLikedNotification extends Notification implements ShouldQueue
{
    use Queueable,
        BaseNotification,
        HasUserActor;

    public array $actorData;

    public $notificationType = Notifications::STORY_LIKED;

    private StoryFrame $frameData;
    private string $reactionUnifiedId;

    public function __construct(StoryFrame $frameData, string $reactionUnifiedId = StoryFrame::PRIVATE_LIKE_UNIFIED_ID)
    {
        $this->actorData = $this->getUserActor();
        $this->frameData = $frameData;
        $this->reactionUnifiedId = strtolower($reactionUnifiedId);
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

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())->subject(__($this->getNotificationSubjectKey(), locale: $notifiable->language))->view($this->notificationViewPath, [
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

    private function getData(): array
    {
        $media = $this->frameData->media->first();

        return [
            'message_group' => 'user',
            'message_key' => $this->isLikeReaction() ? 'story_liked' : 'story_reacted',
            'message_params' => [],
            'metadata' => [
                'reaction_unified_id' => $this->reactionUnifiedId,
            ],
            'entity' => [
                'story_uuid' => $this->frameData->story->story_uuid,
                'content' => $this->cutContent($this->frameData->content),
                'preview_lqip_base64' => $media?->lqip_base64,
            ],
            'actor' => $this->actorData,
        ];
    }

    protected function getDestinationLink(): string
    {
        return url("/stories/{$this->frameData->story->story_uuid}");
    }

    private function isLikeReaction(): bool
    {
        return $this->reactionUnifiedId === StoryFrame::PRIVATE_LIKE_UNIFIED_ID;
    }

    private function getNotificationSubjectKey(): string
    {
        return $this->isLikeReaction()
            ? 'notifications.subjects.story_liked'
            : 'notifications.subjects.story_reacted';
    }
}
