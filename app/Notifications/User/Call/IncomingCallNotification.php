<?php

namespace App\Notifications\User\Call;

use App\Constants\Notifications;
use App\Http\Resources\User\Chat\CallSessionResource;
use App\Models\CallSession;
use App\Notifications\Channels\WebPushChannel;
use App\Services\Notifications\NotificationActionTokenService;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class IncomingCallNotification extends Notification
{
    public $notificationType = Notifications::CALL_INCOMING;

    public function __construct(private CallSession $callSession) {}

    public function broadcastType(): string
    {
        return 'call.notification';
    }

    public function via(object $notifiable): array
    {
        $channels = ['broadcast'];

        if(config('notifications.push.enabled')) {
            array_push($channels, WebPushChannel::class);
        }

        return $channels;
    }

    public function toBroadcast(): BroadcastMessage
    {
        return new BroadcastMessage([
            'data' => [
                'type' => $this->notificationType,
                'call' => CallSessionResource::make($this->callSession)->resolve(),
            ],
        ]);
    }

    public function toPush(object $notifiable): array
    {
        $caller = $this->callSession->initiator;
        $chatUuid = $this->callSession->chat->chat_id;
        $callUuid = $this->callSession->call_uuid;
        $actionToken = app(NotificationActionTokenService::class)->make(
            userId: $notifiable->id,
            chatUuid: $chatUuid,
            actions: ['answer', 'decline', 'message'],
            messageId: null,
            ttlMinutes: 2,
            callUuid: $callUuid
        );

        return [
            'title' => $caller?->name ?: config('app.name', 'Zulors'),
            'body' => 'Incoming voice call',
            'url' => url('/messenger/c/' . $chatUuid . '?' . http_build_query([
                'call' => $callUuid,
                'intent' => 'incoming',
            ])),
            'type' => $this->notificationType,
            'channel_id' => 'zulors_calls',
            'android' => [
                'priority' => 'high',
                'ttl' => '40s',
            ],
            'data' => [
                'call_id' => $callUuid,
                'chat_id' => $chatUuid,
                'media_type' => $this->callSession->media_type->value,
                'ringtone' => 'incoming_call',
                'notification_category' => 'call',
                'notification_visibility' => 'public',
                'caller_id' => $caller?->id,
                'caller_name' => $caller?->name,
                'caller_username' => $caller?->username,
                'caller_avatar_url' => $caller?->avatar_url,
                'action_token' => $actionToken,
            ],
        ];
    }
}
