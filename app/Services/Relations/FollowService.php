<?php

namespace App\Services\Relations;

use App\Enums\User\FollowStatus;
use App\Models\{Follow, User};
use Exception;

class FollowService
{
    private User $followerData;
    private User $followingData;

    /**
     * @param int|User $follower
     * @param int|User $following
     * @throws Exception
     * @return void
     *
     * You can pass either the user ID or the User model instance.
     */

    public function __construct(int|User $follower, int|User $following)
    {
        $this->followerData = $follower instanceof User ? $follower : User::activeById($follower)->first();
        $this->followingData = $following instanceof User ? $following : User::activeById($following)->first();

        if(! $this->followerData || ! $this->followingData) {
            throw new Exception('Follower or following user not found.');
        }
    }

    public function isFollowing(): bool
    {
        return Follow::where([
            'follower_id' => $this->followerData->id,
            'following_id' => $this->followingData->id,
            'status' => FollowStatus::FOLLOWING
        ])->exists();
    }

    public function follow(): Follow
    {
        if($this->followingData->permitSettings->followers->onlyApproved()) {
            return Follow::create([
                'follower_id' => $this->followerData->id,
                'following_id' => $this->followingData->id,
                'status' => FollowStatus::REQUESTED
            ]);
        }
        else {
            $this->followingData->increment('followers_count', 1);
            $this->followerData->increment('following_count', 1);

            return Follow::create([
                'follower_id' => $this->followerData->id,
                'following_id' => $this->followingData->id,
                'status' => FollowStatus::FOLLOWING
            ]);
        }
    }

    public function accept(): void
    {
        $follow = Follow::where([
            'follower_id' => $this->followerData->id,
            'following_id' => $this->followingData->id,
            'status' => FollowStatus::REQUESTED
        ])->first();

        if($follow) {
            $follow->update([
                'status' => FollowStatus::FOLLOWING
            ]);

            $this->followingData->increment('followers_count', 1);
            $this->followerData->increment('following_count', 1);
        }
    }

    public function unfollow(): void
    {
        Follow::where([
            'follower_id' => $this->followerData->id,
            'following_id' => $this->followingData->id,
            'status' => FollowStatus::FOLLOWING
        ])->delete();

        User::where('id', $this->followingData->id)->where('followers_count', '>', 0)->decrement('followers_count');
        User::where('id', $this->followerData->id)->where('following_count', '>', 0)->decrement('following_count');
    }

    public function followRequested(): bool
    {
        return Follow::where([
            'follower_id' => $this->followerData->id,
            'following_id' => $this->followingData->id,
            'status' => FollowStatus::REQUESTED
        ])->exists();
    }

    public function canFollow(): bool
    {
        $blockService = new BlockService($this->followerData, $this->followingData);

        if($blockService->blockedAny()) {
            return false;
        }

        return $this->followerData->id !== $this->followingData->id;
    }
}
