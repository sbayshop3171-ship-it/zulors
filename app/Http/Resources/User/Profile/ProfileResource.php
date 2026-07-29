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

namespace App\Http\Resources\User\Profile;

use App\Constants\Relationship;
use App\Services\Relations\BlockService;
use App\Services\Relations\FollowService;
use App\Services\Relations\MuteService;
use App\Support\Num;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isMe = ($this->id == me()->id);

        $isBlocked = $this->isBlocked();
        $isBlockedBy = $this->isBlockedBy();
        $hideContent = $isBlocked || $isBlockedBy;

        $profileData = [
            'id' => $this->id,
            'name' => $this->name,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'username' => $this->username,
            'caption' => ($hideContent) ? '' : $this->caption,
            'avatar_url' => ($hideContent) ? asset(config('user.avatar')) : $this->avatar_url,
            'cover_url' => ($hideContent) ? asset(config('user.cover')) : $this->cover_url,
            'profile_url' => $this->profile_url,
            'bio' => ($hideContent) ? '' : $this->bio,
            'join_date' => ($hideContent) ? null : [
                'raw' => $this->getCreatedAt()->getTimestamp(),
                'formatted' => $this->getCreatedAt()->getCalendar()
            ],
            'gender' => ($hideContent) ? null : $this->gender,
            'website' => ($hideContent) ? null : $this->website,
            'verified' => $this->verified,
            'publications_count' => ($hideContent) ? null : [
                'raw' => $this->publications_count,
                'formatted' => Num::abbreviate($this->publications_count)
            ],
            'followers_count' => ($hideContent) ? null : [
                'raw' => $this->followers_count,
                'formatted' => Num::abbreviate($this->followers_count)
            ],
            'following_count' => ($hideContent) ? null : [
                'raw' => $this->following_count,
                'formatted' => Num::abbreviate($this->following_count)
            ],
            'meta' => [
                'is_owner' => $isMe,
                'permissions' => [
                    'can_sanction' => (! $isMe && me()->isRoot()),
                    'can_follow' => (new FollowService($this->resource, me()))->canFollow() && ! $hideContent,
                    'can_mention' => (! $isMe && ! $hideContent),
                    'can_message' => (! $isMe && ! $hideContent && ! $this->permitSettings->direct_messages->nobody()),
                    'can_block' => (! $isMe),
                    'can_report' => (! $isMe && ! $hideContent),
                    'can_mute' => (! $isMe && ! $hideContent),
                ],
                'relationship' => [
                    Relationship::FOLLOW_GROUP => [
                        Relationship::FOLLOWING => (new FollowService(me(), $this->resource))->isFollowing(),
                        Relationship::FOLLOWED_BY => (new FollowService($this->resource, me()))->isFollowing(),
                        Relationship::REQUESTED => (new FollowService(me(), $this->resource))->followRequested()
                    ],
                    Relationship::BLOCK_GROUP => [
                        Relationship::BLOCKING => $isBlocked,
                        Relationship::BLOCKED_BY => $isBlockedBy
                    ],
                    Relationship::MUTING_GROUP => [
                        Relationship::MUTING => $this->isMuted(),
                    ]
                ]
            ]
        ];

        if(empty($this->privacySettings->country_privacy) && ! $hideContent) {
            $profileData['country'] = $this->country;
            $profileData['country_name'] = $this->country_name;
        }

        if(empty($this->privacySettings->city_privacy) && ! $hideContent) {
            $profileData['city'] = $this->city;
        }

        return $profileData;
    }

    private function isMuted(): bool
    {
        try {
            $muteService = new MuteService(me(), $this->resource);

            return $muteService->isMuted();
        } catch (Exception $e) {
            return false;
        }
    }

    private function isBlocked(): bool
    {
        try {
            $blockService = new BlockService(me(), $this->resource);

            return $blockService->isBlocked();
        } catch (Exception $e) {
            return false;
        }
    }
    private function isBlockedBy(): bool
    {
        try {
            $blockService = new BlockService($this->resource, me());

            return $blockService->isBlocked();
        } catch (Exception $e) {
            return false;
        }
    }
}
