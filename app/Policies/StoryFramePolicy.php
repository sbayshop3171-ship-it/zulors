<?php

namespace App\Policies;

use App\Models\User;
use App\Models\StoryFrame;

class StoryFramePolicy
{
    public function delete(User $user, StoryFrame $frameData)
    {
        return $user->id === $frameData->story->user_id || $user->isRoot();
    }
}
