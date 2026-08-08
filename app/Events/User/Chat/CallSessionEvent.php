<?php

namespace App\Events\User\Chat;

use App\Http\Resources\User\Chat\CallSessionResource;
use App\Models\CallSession;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CallSessionEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        private string $eventName,
        private CallSession $callSession,
        private array $payload = []
    ) {}

    public function broadcastAs()
    {
        return $this->eventName;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("App.Models.Chat.{$this->callSession->chat->chat_id}")
        ];
    }

    public function broadcastWith()
    {
        return [
            'data' => array_merge([
                'call' => CallSessionResource::make($this->callSession),
            ], $this->payload)
        ];
    }
}
