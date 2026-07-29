<?php

namespace App\Console\Commands\System;

use App\Models\User;
use Illuminate\Console\Command;
use App\Enums\User\FollowStatus;

class AccurateFollowCounts extends Command
{
    protected $signature = 'colibri:accurate-follow';

    protected $description = 'Accurate follow counts for all users. Do not run this command too often.';

    public function handle()
    {
        User::active()->chunk(100, function($users) {
            foreach($users as $user) {
                $user->update([
                    'followers_count' => $user->followers()->where('status', FollowStatus::FOLLOWING)->count(),
                    'following_count' => $user->followings()->where('status', FollowStatus::FOLLOWING)->count()
                ]);
            }
        });

        $this->info('Done!');
    }
}
