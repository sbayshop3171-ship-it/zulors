<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Group;

class GroupPolicy
{
    public function edit(User $user, Group $groupData) {
        return $groupData->user_id === $user->id || $user->isRoot();
    }

    public function update(User $user, Group $groupData) {
        return $groupData->user_id === $user->id || $user->isRoot();
    }

    public function delete(User $user, Group $groupData) {
        return $groupData->user_id === $user->id || $user->isRoot();
    }

    public function leave(User $user, Group $groupData) {
        // Do not let user leave the group if he is the owner.

        return $groupData->user_id !== $user->id;
    }
}
