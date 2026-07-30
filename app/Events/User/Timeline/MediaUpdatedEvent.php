<?php

namespace App\Events\User\Timeline;

use App\Models\Media;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;
use App\Http\Resources\User\Media\MediaResource;

class MediaUpdatedEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(private Media $media, private int $userId)
    {
        //
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("App.Models.User.{$this->userId}")
        ];
    }

    public function broadcastAs(): string
    {
        return 'timeline.media.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'data' => MediaResource::make($this->media)
        ];
    }
}
