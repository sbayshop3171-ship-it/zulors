<?php

namespace App\Http\Resources\User\Overview;

use App\Constants\Relationship;
use App\Services\Relations\FollowService;
use App\Support\Num;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserOverviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'caption' => (empty($this->caption)) ? null : $this->caption,
            'avatar_url' => $this->avatar_url,
            'description' => $this->bio,
            'verified' => $this->isVerified(),
            'is_me' => me()->id === $this->id,
            'followers_count' => [
                'raw' => $this->followers_count,
                'formatted' => Num::abbreviate($this->followers_count)
            ],
            'last_active' => [
                'raw' => $this->getLastActive()->getTimestamp(),
                'formatted' => $this->getLastActive()->getCalendar()
            ],
            'meta' => [
                'relationship' => [
                    Relationship::FOLLOW_GROUP => [
                        Relationship::FOLLOWED_BY => (new FollowService($this->resource, me()))->isFollowing(),
                        Relationship::FOLLOWING => (new FollowService(me(), $this->resource))->isFollowing(),
                        Relationship::REQUESTED => (new FollowService(me(), $this->resource))->followRequested()
                    ],
                    Relationship::BLOCK_GROUP => [
                        Relationship::BLOCKING => false
                    ],
                    Relationship::MUTING_GROUP => [
                        Relationship::MUTING => false,
                    ]
                ]
            ]
        ];
    }
}
