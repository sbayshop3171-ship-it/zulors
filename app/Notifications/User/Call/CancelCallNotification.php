<?php

namespace App\Notifications\User\Call;

use App\Constants\Notifications;
use App\Models\CallSession;
use App\Notifications\Channels\WebPushChannel;
use Illuminate\Notifications\Notification;

class CancelCallNotification extends Notification
{
    public $notificationType = Notifications::CALL_CANCEL;

    public function __construct(private CallSession $callSession) {}

    public function via(object $notifiable): array
    {
        return config('notifications.push.enabled') ? [WebPushChannel::class] : [];
    }

    public function toPush(object $notifiable): array
    {
        $chatUuid = $this->callSession->chat?->chat_id;

        return [
            'title' => '',
            'body' => '',
            'type' => $this->notificationType,
            'channel_id' => 'zulors_calls',
            'data' => [
                'call_id' => $this->callSession->call_uuid,
                'chat_id' => $chatUuid,
                'media_type' => $this->callSession->media_type?->value,
                'call_status' => $this->callSession->status?->value,
                'end_reason' => $this->callSession->end_reason,
                'cancel_notification' => 'true',
            ],
        ];
    }
}
