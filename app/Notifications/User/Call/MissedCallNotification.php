<?php

namespace App\Notifications\User\Call;

use App\Constants\Notifications;
use App\Http\Resources\User\Chat\CallSessionResource;
use App\Models\CallSession;
use App\Notifications\Channels\WebPushChannel;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class MissedCallNotification extends Notification
{
    public $notificationType = Notifications::CALL_MISSED;

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

        return [
            'title' => $caller?->name ?: config('app.name', 'Zulors'),
            'body' => 'Missed voice call',
            'url' => url("/messenger/c/{$chatUuid}"),
            'type' => $this->notificationType,
            'channel_id' => 'zulors_calls',
            'data' => [
                'call_id' => $this->callSession->call_uuid,
                'chat_id' => $chatUuid,
                'media_type' => $this->callSession->media_type->value,
                'caller_id' => $caller?->id,
                'caller_name' => $caller?->name,
                'caller_username' => $caller?->username,
                'caller_avatar_url' => $caller?->avatar_url,
            ],
        ];
    }
}
