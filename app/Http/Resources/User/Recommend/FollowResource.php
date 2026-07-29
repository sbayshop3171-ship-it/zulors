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

namespace App\Http\Resources\User\Recommend;

use App\Constants\Relationship;
use App\Services\Relations\FollowService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FollowResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'avatar_url' => $this->avatar_url,
            'bio' => $this->bio,
            'username' => $this->username,
            'caption' => $this->getCaption(),
            'verified' => $this->isVerified(),
            'meta' => [
                'relationship' => [
                    Relationship::FOLLOW_GROUP => [
                        Relationship::FOLLOWING => (new FollowService(me(), $this->resource))->isFollowing(),
                        Relationship::FOLLOWED_BY => (new FollowService($this->resource, me()))->isFollowing(),
                        Relationship::REQUESTED => (new FollowService(me(), $this->resource))->followRequested()
                    ]
                ]
            ]
        ];
    }
}
