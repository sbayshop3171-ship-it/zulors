<?php

namespace App\Actions\Recommend;

use App\Models\User;
use App\Database\Configs\Table;
use App\Enums\User\FollowStatus;
use Illuminate\Support\Facades\DB;

class FetchFollowRecommendation
{
    public function handle(int $limit = 5)
    {
        $limit = max(1, $limit);
        $viewer = me();
        $followingIds = $this->followingIds($viewer->id);

        $recommendations = User::active()->author()->excludeSelf()->whereNotIn('id', function ($query) {
            $query->select('following_id')->from(Table::FOLLOWS)->where('follower_id', me()->id);
        })->whereNotIn('id', function ($query) {
            $query->select('blocked_id')->from(Table::BLOCKS)->where('blocker_id', me()->id);
        })->whereNotIn('id', function ($query) {
            $query->select('blocker_id')->from(Table::BLOCKS)->where('blocked_id', me()->id);
        })->withCount(['followers as mutual_followers_count' => function($query) use ($followingIds) {
            if(empty($followingIds)) {
                $query->whereRaw('1 = 0');
            }
            else {
                $query->whereIn('follower_id', $followingIds)
                    ->where('status', FollowStatus::FOLLOWING->value);
            }
        }])->limit(max($limit * 8, 30))
            ->orderByDesc('followers_count')
            ->orderByDesc('publications_count')
            ->get();

        $topicMatches = $this->topicMatches($recommendations->pluck('id')->all(), $this->userTopics($viewer));

        return $recommendations->sortByDesc(function(User $user) use ($viewer, $topicMatches) {
            return $this->scoreUser($viewer, $user, (int) data_get($topicMatches, $user->id, 0));
        })->take($limit)->values();
    }

    private function scoreUser(User $viewer, User $user, int $topicMatches): float
    {
        $activeAt = is_numeric($user->last_active) ? (int) $user->last_active : 0;
        $daysSinceActive = $activeAt ? max(0, (time() - $activeAt) / 86400) : 365;
        $activityScore = max(0, 12 - min(12, $daysSinceActive));
        $jitter = ((int) sprintf('%u', crc32("{$viewer->id}:{$user->id}:follow"))) % 1000 / 1000;

        return ($topicMatches * 30)
            + ((int) $user->mutual_followers_count * 14)
            + (log(((int) $user->followers_count) + 1) * 4)
            + (log(((int) $user->publications_count) + 1) * 3)
            + ($user->verified ? 8 : 0)
            + $activityScore
            + $jitter;
    }

    private function followingIds(int $userId): array
    {
        return DB::table(Table::FOLLOWS)
            ->where('follower_id', $userId)
            ->where('status', FollowStatus::FOLLOWING->value)
            ->pluck('following_id')
            ->map(fn($id) => (int) $id)
            ->all();
    }

    private function userTopics(User $user): array
    {
        return $user->interestScores()
            ->where('score', '>', 0)
            ->orderByDesc('score')
            ->limit(12)
            ->pluck('topic')
            ->all();
    }

    private function topicMatches(array $userIds, array $topics): array
    {
        if(empty($userIds) || empty($topics)) {
            return [];
        }

        return DB::table(Table::POSTS)
            ->join(Table::POST_TOPICS, Table::POST_TOPICS . '.post_id', '=', Table::POSTS . '.id')
            ->select(Table::POSTS . '.user_id', DB::raw('COUNT(DISTINCT ' . Table::POST_TOPICS . '.topic) as topic_matches'))
            ->whereIn(Table::POSTS . '.user_id', $userIds)
            ->whereIn(Table::POST_TOPICS . '.topic', $topics)
            ->groupBy(Table::POSTS . '.user_id')
            ->pluck('topic_matches', Table::POSTS . '.user_id')
            ->map(fn($count) => (int) $count)
            ->all();
    }
}
