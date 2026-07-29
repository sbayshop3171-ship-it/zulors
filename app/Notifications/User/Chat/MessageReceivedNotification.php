<?php

namespace App\Notifications\User\Chat;

use App\Models\Message;
use Illuminate\Notifications\Notification;
use App\Http\Resources\User\Chat\MessageResource;
use Illuminate\Notifications\Messages\BroadcastMessage;

class MessageReceivedNotification extends Notification
{
    public function __construct(private ?Message $messageData = null) {}

    public function broadcastType(): string
    {
        return 'chat.notification';
    }

    public function via(object $notifiable): array
    {
        return ['broadcast'];
    }

    public function toBroadcast(): BroadcastMessage
    {
        return new BroadcastMessage([
            'data' => ($this->messageData) ? MessageResource::make($this->messageData)->resolve() : []
        ]);
    }
}
