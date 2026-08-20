<?php

namespace App\Events\User\Chat;

use App\Http\Resources\User\Chat\MessageResource;
use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageMediaReadyEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    private Message $messageData;

    public function __construct(Message $messageData)
    {
        $this->messageData = $messageData;
    }

    public function broadcastAs(): string
    {
        return 'chat.media.ready';
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("App.Models.Chat.{$this->messageData->chat_uuid}")
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'data' => MessageResource::make($this->messageData)
        ];
    }
}
