<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;

class PostPolicy
{
    public function update(User $user, Post $postData) {
        return $postData->user_id === $user->id || $user->isAdmin() || $user->isRoot();
    }

    public function delete(User $user, Post $postData) {
        return $postData->user_id === $user->id || $user->isRoot();
    }
}
