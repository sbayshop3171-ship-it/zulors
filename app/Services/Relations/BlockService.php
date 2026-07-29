<?php

namespace App\Services\Relations;

use App\Models\Block;
use App\Models\User;
use Exception;

class BlockService
{
    private User $blockerData;
    private User $blockedData;

    public function __construct(int|User $blocker, int|User $blocked)
    {
        $this->blockerData = $blocker instanceof User ? $blocker : User::activeById($blocker)->first();
        $this->blockedData = $blocked instanceof User ? $blocked : User::activeById($blocked)->first();

        if(! $this->blockerData || ! $this->blockedData) {
            throw new Exception('Blocker or blocked user not found.');
        }
    }

    public function isBlocked(): bool
    {
        return $this->checkBlocked($this->blockerData->id, $this->blockedData->id);
    }

    public function blockedAny(): bool
    {
        return $this->checkBlocked($this->blockerData->id, $this->blockedData->id) || $this->checkBlocked($this->blockedData->id, $this->blockerData->id);
    }

    public function block(): void
    {
        Block::firstOrCreate([
            'blocker_id' => $this->blockerData->id,
            'blocked_id' => $this->blockedData->id,
        ]);

        // If user is blocked, they should be muted and unfollowed by the blocker.

        (new MuteService($this->blockerData, $this->blockedData))->mute();

        // Unfollow in both directions.
        (new FollowService($this->blockerData, $this->blockedData))->unfollow();
        (new FollowService($this->blockedData, $this->blockerData))->unfollow();
    }

    public function unblock(): void
    {
        Block::where('blocker_id', $this->blockerData->id)->where('blocked_id', $this->blockedData->id)->delete();

        // If user is unblocked, they should be unmuted by the blocker.
        (new MuteService($this->blockerData, $this->blockedData))->unmute();
    }

    private function checkBlocked(int $blockerId, int $blockedId): bool
    {
        return Block::where('blocker_id', $blockerId)->where('blocked_id', $blockedId)->exists();
    }
}
