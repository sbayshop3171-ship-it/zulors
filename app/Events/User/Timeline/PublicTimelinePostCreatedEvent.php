<?php

namespace App\Events\User\Timeline;

use App\Models\Post;
use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class PublicTimelinePostCreatedEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    private Post $postData;

    public function __construct(Post $postData)
    {
        $this->postData = $postData;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('timeline.public')
        ];
    }

    public function broadcastAs(): string
    {
        return 'timeline.post.created';
    }

    public function broadcastWith(): array
    {
        return [
            'data' => [
                'post_id' => $this->postData->id,
                'user_id' => $this->postData->user_id,
                'status' => $this->postData->status?->value,
                'created_at' => $this->postData->created_at?->toJSON()
            ]
        ];
    }
}
