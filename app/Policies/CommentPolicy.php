<?php

namespace App\Policies;

use App\Models\Comment;
use App\Models\User;

class CommentPolicy
{
    public function update(User $user, Comment $commentData) {
        return (int) $commentData->user_id === (int) $user->id
            && \Illuminate\Support\Carbon::parse($commentData->getRawOriginal('created_at'))->gte(now()->subMinutes(15));
    }

    public function delete(User $user, Comment $commentData) {
        return $commentData->user_id === $user->id || $commentData->post->user_id === $user->id || $user->isRoot();
    }
}
