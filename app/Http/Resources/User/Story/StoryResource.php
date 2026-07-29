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

namespace App\Http\Resources\User\Story;

use Illuminate\Http\Request;
use App\Http\Resources\User\Media\MediaResource;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\User\User\UserPreviewResource;

class StoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isOwner = me()->isRoot() || (me()->id === $this->user_id);

        return [
            'id' => $this->id,
            'story_uuid' => $this->story_uuid,
            // TODO: This is a temporary solution to get the story URL.
            // We need to implement a proper solution in future updates.
            
            'url' => url("stories/{$this->story_uuid}"),
            'relations' => [
                'user' => UserPreviewResource::make($this->user),
                'frames' => $this->frames->map(function($frameItem) {
                    return [
                        'id' => $frameItem->id,
                        'type' => $frameItem->type->value,
                        'content' => $frameItem->content,
                        'media' => $this->getStoryMedia($frameItem),
                        'meta' => $frameItem->metadata,
                        'duration_seconds' => $frameItem->duration_seconds,
                        'views_count' => [
                            'raw' => $frameItem->views_count,
                            'formatted' => $frameItem->views_count,
                        ],
                        'relations' => [
                            'views' => []
                        ],
                        'date' => [
                            'time_ago' => $frameItem->created_at->getTimeAgo()
                        ],
                        'activity' => [
                            'is_seen' => $this->checkIfStoryFrameSeen($frameItem)
                        ]
                    ];
                })
            ],
            'permissions' => [
                'can_delete' => $isOwner,
                'can_hide' => empty($isOwner),
                'can_report' => empty($isOwner),
            ],
            'meta' => [
                'is_owner' => $isOwner
            ]
        ];
    }

    private function checkIfStoryFrameSeen($frameItem)
    {
        return $frameItem->views()->where('user_id', me()->id)->exists();
    }

    private function getStoryMedia($frameItem)
    {
        return MediaResource::make($frameItem->media->first());
    }
}
