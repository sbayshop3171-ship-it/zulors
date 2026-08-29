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
use App\Services\RealTime\NonBlockingBroadcaster;
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
    private float $startTime;

    public function __construct(Message $messageData, ?string $clientUid = null)
    {
        $this->messageData = $messageData;
        $this->clientUid = $clientUid;
        $this->startTime = microtime(true);
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

        // Add latency tracking
        $elapsedMs = (microtime(true) - $this->startTime) * 1000;
        $payload['meta']['server_broadcast_time_ms'] = round($elapsedMs, 2);

        return [
            'data' => $payload
        ];
    }

    /**
     * Use non-blocking broadcast for instant delivery
     * Heavy DB operations run async after event is published
     */
    public function shouldBroadcast()
    {
        // Broadcast immediately via Redis pub/sub
        $broadcaster = app(NonBlockingBroadcaster::class);
        $broadcaster->broadcastInstant($this, 'high');

        // Return false to prevent Laravel's default broadcasting
        return false;
    }
}
