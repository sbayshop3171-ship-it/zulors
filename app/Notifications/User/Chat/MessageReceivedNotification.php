<?php

namespace App\Notifications\User\Chat;

use App\Models\Message;
use Illuminate\Support\Str;
use App\Constants\Notifications;
use Illuminate\Notifications\Notification;
use App\Notifications\Channels\WebPushChannel;
use App\Http\Resources\User\Chat\MessageResource;
use Illuminate\Notifications\Messages\BroadcastMessage;

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

        if($this->messageData && $notifiable->pushNotificationSettings?->direct_messages && config('notifications.push.enabled')) {
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
        $chatId = $this->messageData?->chat_id;
        $text = trim((string) ($this->messageData?->content ?? ''));

        return [
            'title' => $sender?->name ?: config('app.name', 'Zulors'),
            'body' => filled($text) ? Str::limit($text, 140) : __('notifications.chat.message_received', locale: $notifiable->language),
            'url' => url($chatId ? "/messenger/c/{$chatId}" : '/messenger'),
            'type' => $this->notificationType,
            'channel_id' => 'zulors_messages',
            'data' => [
                'chat_id' => $chatId,
                'message_id' => $this->messageData?->id,
                'sender_id' => $sender?->id,
                'sender_name' => $sender?->name,
                'sender_username' => $sender?->username,
                'sender_avatar_url' => $sender?->avatar_url,
            ],
        ];
    }
}
