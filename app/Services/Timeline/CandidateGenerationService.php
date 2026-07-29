<?php

namespace App\Services\Timeline;

use App\Database\Configs\Table;
use App\Enums\User\FollowStatus;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CandidateGenerationService
{
    public const DEFAULT_CANDIDATE_LIMIT = 150;
    public const MIN_CANDIDATE_LIMIT = 100;
    public const MAX_CANDIDATE_LIMIT = 150;

    public function getCandidates(User $user, string $type, array $filter = []): Collection
    {
        $limit = $this->candidateLimit(data_get_integer($filter, 'candidate_limit', self::DEFAULT_CANDIDATE_LIMIT));
        $query = $this->baseTimelineQuery($user, data_get_integer($filter, 'onset', 0));

        $this->applyFeedType($query, $user, $type);

        return $query
            ->orderBy('created_at', 'desc')
            ->orderBy('comments_count', 'desc')
            ->orderBy('bookmarks_count', 'desc')
            ->orderBy('shares_count', 'desc')
            ->orderBy('views_count', 'desc')
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->get();
    }

    public function latestQuery(User $user, array $filter = []): Builder
    {
        return $this->baseTimelineQuery($user, data_get_integer($filter, 'onset', 0))
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc');
    }

    public function candidateLimit(int $limit): int
    {
        return max(self::MIN_CANDIDATE_LIMIT, min(self::MAX_CANDIDATE_LIMIT, $limit));
    }

    private function baseTimelineQuery(User $user, int $onset): Builder
    {
        return Post::timelineFormatPosts()
            ->with('topics')
            ->with('videoMetric')
            ->with('user.safetyScore')
            ->withCount('reports')
            ->when($onset, function($query) use ($onset) {
                $query->where('id', '>', $onset);
            })
            ->when((! $user->isRoot()), function($query) use ($user) {
                $query->where(function($query) use ($user) {
                    $query->where('user_id', $user->id)->orWhereHas('user', function($u) {
                        $u->author()->active();
                    });
                });
            })
            ->whereNotIn('user_id', function($query) use ($user) {
                $query->select('blocked_id')->from(Table::BLOCKS)->where('blocker_id', $user->id);
            })
            ->whereNotIn('user_id', function($query) use ($user) {
                $query->select('blocker_id')->from(Table::BLOCKS)->where('blocked_id', $user->id);
            })
            ->whereNotIn('user_id', function($query) use ($user) {
                $query->select('muted_id')->from(Table::MUTES)->where('muter_id', $user->id);
            });
    }

    private function applyFeedType(Builder $query, User $user, string $type): void
    {
        if($type === FeedService::TYPE_FOLLOWING) {
            $query->whereIn('user_id', function($query) use ($user) {
                $query->select('following_id')
                    ->from(Table::FOLLOWS)
                    ->where('follower_id', $user->id)
                    ->where('status', FollowStatus::FOLLOWING->value);
            });
        }
    }
}
