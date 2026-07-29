<?php

namespace App\Events\User\Timeline;

use App\Models\Post;
use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class PostUpdatedEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(private Post $postData)
    {
        //
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('timeline.public')
        ];
    }

    public function broadcastAs(): string
    {
        return 'timeline.post.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'data' => [
                'post_id' => $this->postData->id,
                'hash_id' => $this->postData->hash_id,
                'user_id' => $this->postData->user_id,
                'updated_at' => (string) $this->postData->getRawOriginal('updated_at')
            ]
        ];
    }
}
