<?php

namespace App\Policies;

use App\Models\Comment;
use App\Models\User;

class CommentPolicy
{
    public function update(User $user, Comment $commentData) {
        return $commentData->user_id === $user->id || $commentData->post->user_id === $user->id || $user->isRoot();
    }

    public function delete(User $user, Comment $commentData) {
        return $commentData->user_id === $user->id || $commentData->post->user_id === $user->id || $user->isRoot();
    }
}
