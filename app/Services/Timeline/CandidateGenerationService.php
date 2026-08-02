<?php

namespace App\Services\Timeline;

use App\Database\Configs\Table;
use App\Enums\Media\MediaStatus;
use App\Enums\Media\MediaType;
use App\Enums\Post\PostType;
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
        $onset = data_get_integer($filter, 'onset', 0);

        if($type === FeedService::TYPE_FOLLOWING) {
            $query = $this->baseTimelineQuery($user, $onset, $type);
            $this->applyFeedType($query, $user, $type);

            return $this->orderByRecencyAndEngagement($query)
                ->limit($limit)
                ->get();
        }

        return $this->forYouCandidates($user, $onset, $limit, $type);
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

    public function seedReelPost(User $user, string $hashId): ?Post
    {
        $hashId = trim($hashId);

        if($hashId === '') {
            return null;
        }

        return $this->baseTimelineQuery($user, 0, FeedService::TYPE_REELS)
            ->whereHashId($hashId)
            ->first();
    }

    private function baseTimelineQuery(User $user, int $onset, string $type = ''): Builder
    {
        $query = Post::timelineFormatPosts()
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

        if($type === FeedService::TYPE_REELS) {
            $this->applyReelsFilter($query);
        }

        return $query;
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

    private function forYouCandidates(User $user, int $onset, int $limit, string $type = FeedService::TYPE_FOR_YOU): Collection
    {
        $selected = collect();
        $followedAuthorIds = $this->followedAuthorIds($user);
        $userTopics = $this->positiveUserTopics($user);
        $mix = $this->candidateMixForType($type);

        $this->appendCandidates($selected, $this->followedCandidates($user, $onset, $followedAuthorIds, (int) ceil($limit * $mix['followed']), $type));
        $this->appendCandidates($selected, $this->interestCandidates($user, $onset, $userTopics, $this->selectedIds($selected), (int) ceil($limit * $mix['interest']), $type));
        $this->appendCandidates($selected, $this->recentCandidates($user, $onset, $this->selectedIds($selected), (int) ceil($limit * $mix['recent']), $type));
        $this->appendCandidates($selected, $this->oldGoodCandidates($user, $onset, $this->selectedIds($selected), $limit - $selected->count(), $type));

        if($selected->count() < $limit) {
            $this->appendCandidates($selected, $this->recentCandidates($user, $onset, $this->selectedIds($selected), $limit - $selected->count(), $type));
        }

        return $selected->unique('id')->take($limit)->values();
    }

    private function followedCandidates(User $user, int $onset, array $followedAuthorIds, int $limit, string $type): Collection
    {
        if(empty($followedAuthorIds) || $limit <= 0) {
            return collect();
        }

        $query = $this->baseTimelineQuery($user, $onset, $type)
            ->whereIn('user_id', $followedAuthorIds);

        return $this->orderByRecencyAndEngagement($query)->limit($limit)->get();
    }

    private function interestCandidates(User $user, int $onset, array $topics, array $excludeIds, int $limit, string $type): Collection
    {
        if(empty($topics) || $limit <= 0) {
            return collect();
        }

        $query = $this->baseTimelineQuery($user, $onset, $type)
            ->whereHas('topics', function($query) use ($topics) {
                $query->whereIn('topic', $topics);
            });

        $this->excludeSelected($query, $excludeIds);

        return $this->orderByRecencyAndEngagement($query)->limit($limit)->get();
    }

    private function recentCandidates(User $user, int $onset, array $excludeIds, int $limit, string $type): Collection
    {
        if($limit <= 0) {
            return collect();
        }

        $query = $this->baseTimelineQuery($user, $onset, $type);
        $this->excludeSelected($query, $excludeIds);

        return $this->orderByRecencyAndEngagement($query)->limit($limit)->get();
    }

    private function oldGoodCandidates(User $user, int $onset, array $excludeIds, int $limit, string $type): Collection
    {
        if($limit <= 0) {
            return collect();
        }

        $query = $this->baseTimelineQuery($user, $onset, $type)
            ->where('created_at', '<=', now()->subDay());

        $this->excludeSelected($query, $excludeIds);

        return $this->orderByEngagement($query)->limit($limit)->get();
    }

    private function appendCandidates(Collection $selected, Collection $candidates): void
    {
        $existingIds = $this->selectedIds($selected);

        $candidates->each(function(Post $post) use ($selected, &$existingIds) {
            if(! in_array((int) $post->id, $existingIds, true)) {
                $selected->push($post);
                $existingIds[] = (int) $post->id;
            }
        });
    }

    private function selectedIds(Collection $selected): array
    {
        return $selected->pluck('id')->map(fn($id) => (int) $id)->all();
    }

    private function excludeSelected(Builder $query, array $excludeIds): void
    {
        if(! empty($excludeIds)) {
            $query->whereNotIn('id', $excludeIds);
        }
    }

    private function followedAuthorIds(User $user): array
    {
        return collect(\Illuminate\Support\Facades\DB::table(Table::FOLLOWS)
            ->where('follower_id', $user->id)
            ->where('status', FollowStatus::FOLLOWING->value)
            ->pluck('following_id'))
            ->map(fn($id) => (int) $id)
            ->all();
    }

    private function positiveUserTopics(User $user): array
    {
        return $user->interestScores()
            ->where('score', '>', 0)
            ->orderByDesc('score')
            ->limit(12)
            ->pluck('topic')
            ->all();
    }

    private function orderByRecencyAndEngagement(Builder $query): Builder
    {
        return $query
            ->orderBy('created_at', 'desc')
            ->orderBy('comments_count', 'desc')
            ->orderBy('bookmarks_count', 'desc')
            ->orderBy('shares_count', 'desc')
            ->orderBy('views_count', 'desc')
            ->orderBy('id', 'desc');
    }

    private function orderByEngagement(Builder $query): Builder
    {
        return $query
            ->orderBy('comments_count', 'desc')
            ->orderBy('bookmarks_count', 'desc')
            ->orderBy('shares_count', 'desc')
            ->orderBy('views_count', 'desc')
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc');
    }

    private function applyReelsFilter(Builder $query): void
    {
        $query
            ->where('type', PostType::VIDEO->value)
            ->whereHas('media', function($mediaQuery) {
                $mediaQuery
                    ->where('type', MediaType::VIDEO->value)
                    ->where('status', MediaStatus::PROCESSED->value);
            });
    }

    private function candidateMixForType(string $type): array
    {
        if($type === FeedService::TYPE_REELS) {
            return [
                'followed' => 0.35,
                'interest' => 0.20,
                'recent' => 0.20,
            ];
        }

        return [
            'followed' => 0.65,
            'interest' => 0.15,
            'recent' => 0.10,
        ];
    }
}
