<?php

namespace App\Notifications\User\Chat;

use App\Models\Message;
use Illuminate\Support\Str;
use App\Constants\Notifications;
use Illuminate\Notifications\Notification;
use App\Notifications\Channels\WebPushChannel;
use App\Http\Resources\User\Chat\MessageResource;
use App\Services\Notifications\NotificationActionTokenService;
use App\Services\Notifications\UnreadBadgeCountService;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Support\Carbon;

class MessageReceivedNotification extends Notification
{
    public $notificationType = Notifications::CHAT_MESSAGE_RECEIVED;

    public function __construct(private ?Message $messageData = null) {}

    public function broadcastType(): string
    {
        return 'chat.notification';
    }

    public function via(object $notifiable): array
    {
        $channels = ['broadcast'];

        if($this->messageData
            && $notifiable->pushNotificationSettings?->direct_messages
            && ! $this->isMutedFor($notifiable)
            && config('notifications.push.enabled')) {
            array_push($channels, WebPushChannel::class);
        }

        return $channels;
    }

    public function toBroadcast(): BroadcastMessage
    {
        return new BroadcastMessage([
            'data' => ($this->messageData) ? MessageResource::make($this->messageData)->resolve() : []
        ]);
    }

    public function toPush(object $notifiable): array
    {
        $sender = $this->messageData?->user;
        $chatUuid = $this->messageData?->chat_uuid;
        $text = trim((string) ($this->messageData?->content ?? ''));
        $showPreview = $notifiable->pushNotificationSettings?->show_message_preview ?? true;
        $body = ($showPreview && filled($text))
            ? Str::limit(html_entity_decode(strip_tags($text)), 140)
            : __('notifications.chat.message_received', locale: $notifiable->language);
        $actionToken = ($chatUuid)
            ? app(NotificationActionTokenService::class)->make(
                userId: $notifiable->id,
                chatUuid: $chatUuid,
                actions: ['reply', 'read', 'mute'],
                messageId: $this->messageData?->id
            )
            : null;

        return [
            'title' => $sender?->name ?: config('app.name', 'Zulors'),
            'body' => $body,
            'url' => url($chatUuid ? "/messenger/c/{$chatUuid}" : '/messenger'),
            'type' => $this->notificationType,
            'channel_id' => 'zulors_messages',
            'data' => [
                'chat_id' => $chatUuid,
                'chat_pk' => $this->messageData?->chat_id,
                'message_id' => $this->messageData?->id,
                'sender_id' => $sender?->id,
                'sender_name' => $sender?->name,
                'sender_username' => $sender?->username,
                'sender_avatar_url' => $sender?->avatar_url,
                'action_token' => $actionToken,
                'badge_count' => app(UnreadBadgeCountService::class)->forUser($notifiable),
            ],
        ];
    }

    private function isMutedFor(object $notifiable): bool
    {
        $mutedUntil = $this->messageData?->chat
            ?->participants()
            ->where('user_id', $notifiable->id)
            ->value('notifications_muted_until');

        return filled($mutedUntil) && now()->lt(Carbon::parse($mutedUntil));
    }
}
