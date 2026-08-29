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

use App\Http\Resources\User\Chat\MessageResource;
use App\Models\Message;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class MessageReceivedEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    private $messageData;
    private ?string $clientUid;

    public function __construct(Message $messageData, ?string $clientUid = null)
    {
        $this->messageData = $messageData;
        $this->clientUid = $clientUid;
    }

    public function broadcastAs()
    {
        return 'chat.message.received';
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("App.Models.Chat.{$this->messageData->chat_uuid}")
        ];
    }

    public function broadcastWith()
    {
        $payload = MessageResource::make($this->messageData)->resolve();

        if(! empty($this->clientUid)) {
            $payload['meta']['client_uid'] = $this->clientUid;
        }

        return [
            'data' => $payload
        ];
    }
}
