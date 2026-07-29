<?php
/*
|--------------------------------------------------------------------------
| Zulors - The Zulors Web Application.
|--------------------------------------------------------------------------
| Author: Mansur Terla. Full-Stack Web Developer, UI/UX Designer.
| Website: www.terla.me
| E-mail: mansurtl.contact@gmail.com
| Instagram: @mansur_terla
| Telegram: @mansurtl_contact
|--------------------------------------------------------------------------
| Copyright (c)  Zulors. All rights reserved.
|--------------------------------------------------------------------------
*/

namespace App\Events\User\Chat;

use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class MessageDeletedEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    private int $messageId;
    private string $chatUuid;

    /**
     * Create a new event instance.
     */
    public function __construct(int $messageId, string $chatUuid)
    {
        $this->messageId = $messageId;
        $this->chatUuid = $chatUuid;
    }

    public function broadcastAs()
    {
        return 'chat.message.deleted';
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("App.Models.Chat.{$this->chatUuid}")
        ];
    }

    public function broadcastWith()
    {
        return [
            'data' => [
                'message_id' => $this->messageId,
                'chat_uuid' => $this->chatUuid
            ]
        ];
    }
}
