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

namespace App\Events\User\Timeline;

use App\Models\Media;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use App\Http\Resources\User\Media\MediaResource;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class MediaProcessedEvent implements ShouldBroadcastNow
{
    use InteractsWithSockets, Dispatchable, SerializesModels;

    private $media;
    private $userId;

    /**
     * Create a new event instance.
     */
    public function __construct(Media $media, int $userId)
    {
        $this->media = $media;
        $this->userId = $userId;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("App.Models.User.{$this->userId}")
        ];
    }

    public function broadcastAs()
    {
        return "timeline.media.processed";
    }

    public function broadcastWith()
    {
        return [
            'data' => MediaResource::make($this->media)
        ];
    }
}
