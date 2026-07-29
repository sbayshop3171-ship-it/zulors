<?php

namespace App\Actions\Recommend;

use App\Models\User;
use App\Database\Configs\Table;

class FetchFollowRecommendation
{
    public function handle(int $limit = 5)
    {
        $recommendations = User::active()->author()->excludeSelf()->whereNotIn('id', function ($query) {
            $query->select('following_id')->from(Table::FOLLOWS)->where('follower_id', me()->id);
        })->whereNotIn('id', function ($query) {
            $query->select('blocked_id')->from(Table::BLOCKS)->where('blocker_id', me()->id);
        })->whereNotIn('id', function ($query) {
            $query->select('blocker_id')->from(Table::BLOCKS)->where('blocked_id', me()->id);
        })->limit($limit)
        ->orderByDesc('followers_count')
        ->orderByDesc('publications_count')
        ->inRandomOrder()
        ->get();

        return $recommendations;
    }
}
