<?php

namespace App\Services\Timeline;

use App\Database\Configs\Table;
use App\Enums\Media\MediaStatus;
use App\Enums\Media\MediaType;
use App\Enums\Post\PostType;
use App\Enums\User\FollowStatus;
use App\Models\Post;
use App\Models\PostVideoMetric;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CandidateGenerationService
{
    public const DEFAULT_CANDIDATE_LIMIT = 150;
    public const MIN_CANDIDATE_LIMIT = 100;
    public const MAX_CANDIDATE_LIMIT = 150;

    public function __construct(
        private UserInterestService $userInterestService
    ) {
    }

    public function getCandidates(User $user, string $type, array $filter = []): Collection
    {
        $limit = $this->candidateLimit(data_get_integer($filter, 'candidate_limit', self::DEFAULT_CANDIDATE_LIMIT));
        $onset = data_get_integer($filter, 'onset', 0);

        if($type === FeedService::TYPE_FOLLOWING) {
            $query = $this->baseTimelineQuery($user, $onset, $type);
            $this->applyFeedType($query, $user, $type);

            return $this->tagCandidateSource(
                $this->orderByRecencyAndEngagement($query, $type)
                ->limit($limit)
                ->get(),
                'followed'
            );
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
                        $u->active();
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
        if($type === FeedService::TYPE_REELS && $this->isReelsRankingV2Enabled()) {
            return $this->reelsV2Candidates($user, $onset, $limit);
        }

        if($type === FeedService::TYPE_FOR_YOU && $this->isHomeRankingV2Enabled()) {
            return $this->homeV2Candidates($user, $onset, $limit);
        }

        $selected = collect();
        $followedAuthorIds = $this->followedAuthorIds($user);
        $userTopics = $this->positiveUserTopics($user);
        $mix = $this->candidateMixForType($type);
        $seenExcludeIds = $type === FeedService::TYPE_REELS
            ? $this->recentlySeenReelIds($user)
            : [];
        $feedbackExcludeIds = $type === FeedService::TYPE_REELS
            ? $this->feedbackSuppressedReelIds($user)
            : [];
        $baselineExcludeIds = $this->mergeExcludedIds($seenExcludeIds, $feedbackExcludeIds);

        $this->appendCandidates($selected, $this->followedCandidates(
            $user,
            $onset,
            $followedAuthorIds,
            $this->mergeExcludedIds([], $baselineExcludeIds),
            (int) ceil($limit * $mix['followed']),
            $type,
            'followed'
        ));
        $this->appendCandidates($selected, $this->interestCandidates(
            $user,
            $onset,
            $userTopics,
            $this->mergeExcludedIds($this->selectedIds($selected), $baselineExcludeIds),
            (int) ceil($limit * $mix['interest']),
            $type,
            'interest'
        ));
        $this->appendCandidates($selected, $this->recentCandidates(
            $user,
            $onset,
            $this->mergeExcludedIds($this->selectedIds($selected), $baselineExcludeIds),
            (int) ceil($limit * $mix['recent']),
            $type,
            'recent'
        ));
        $this->appendCandidates($selected, $this->oldGoodCandidates(
            $user,
            $onset,
            $this->mergeExcludedIds($this->selectedIds($selected), $baselineExcludeIds),
            $limit - $selected->count(),
            $type,
            'old_good'
        ));

        if($selected->count() < $limit) {
            $this->appendCandidates($selected, $this->recentCandidates(
                $user,
                $onset,
                $this->mergeExcludedIds($this->selectedIds($selected), $feedbackExcludeIds),
                $limit - $selected->count(),
                $type,
                'recent'
            ));
        }

        if($type === FeedService::TYPE_REELS && $selected->count() < $limit) {
            $this->appendCandidates($selected, $this->oldGoodCandidates(
                $user,
                $onset,
                $this->mergeExcludedIds($this->selectedIds($selected), $feedbackExcludeIds),
                $limit - $selected->count(),
                $type,
                'old_good'
            ));
        }

        return $selected->unique('id')->take($limit)->values();
    }

    private function homeV2Candidates(User $user, int $onset, int $limit): Collection
    {
        $selected = collect();
        $followedAuthorIds = $this->followedAuthorIds($user);
        $interactedAuthorIds = $this->interactedAuthorIds($user);
        $userTopics = $this->positiveUserTopics($user);
        $isColdUser = ! $this->hasWarmSignals($followedAuthorIds, $interactedAuthorIds, $userTopics);

        if($isColdUser) {
            $this->appendCandidates($selected, $this->recentCandidates($user, $onset, [], (int) ceil($limit * 0.50), FeedService::TYPE_FOR_YOU, 'recent_safe'));
            $this->appendCandidates($selected, $this->popularCandidates($user, $onset, $this->selectedIds($selected), (int) ceil($limit * 0.30), FeedService::TYPE_FOR_YOU, 'popular_general'));
            $this->appendCandidates($selected, $this->explorationCandidates($user, $onset, $this->selectedIds($selected), $limit - $selected->count(), FeedService::TYPE_FOR_YOU, 'exploration'));

            return $selected->unique('id')->take($limit)->values();
        }

        $this->appendCandidates($selected, $this->followedCandidates($user, $onset, $followedAuthorIds, [], (int) ceil($limit * 0.45), FeedService::TYPE_FOR_YOU, 'followed'));
        $this->appendCandidates($selected, $this->authorAffinityCandidates($user, $onset, $interactedAuthorIds, $this->selectedIds($selected), (int) ceil($limit * 0.20), FeedService::TYPE_FOR_YOU, 'interacted_author'));
        $this->appendCandidates($selected, $this->interestCandidates($user, $onset, $userTopics, $this->selectedIds($selected), (int) ceil($limit * 0.15), FeedService::TYPE_FOR_YOU, 'topic_match'));
        $this->appendCandidates($selected, $this->recentCandidates($user, $onset, $this->selectedIds($selected), (int) ceil($limit * 0.10), FeedService::TYPE_FOR_YOU, 'fresh_exploration'));
        $this->appendCandidates($selected, $this->oldGoodCandidates($user, $onset, $this->selectedIds($selected), $limit - $selected->count(), FeedService::TYPE_FOR_YOU, 'old_good_recovery'));

        if($selected->count() < $limit) {
            $this->appendCandidates($selected, $this->explorationCandidates($user, $onset, $this->selectedIds($selected), $limit - $selected->count(), FeedService::TYPE_FOR_YOU, 'fresh_exploration'));
        }

        return $selected->unique('id')->take($limit)->values();
    }

    private function reelsV2Candidates(User $user, int $onset, int $limit): Collection
    {
        $selected = collect();
        $followedAuthorIds = $this->followedAuthorIds($user);
        $affinityAuthorIds = $this->interactedAuthorIds($user, 24);
        $userTopics = $this->positiveUserTopics($user);
        $seenExcludeIds = $this->recentlySeenReelIds($user);
        $feedbackExcludeIds = $this->feedbackSuppressedReelIds($user);
        $baselineExcludeIds = $this->mergeExcludedIds($seenExcludeIds, $feedbackExcludeIds);

        $this->appendCandidates($selected, $this->authorAffinityCandidates($user, $onset, $affinityAuthorIds, $baselineExcludeIds, (int) ceil($limit * 0.26), FeedService::TYPE_REELS, 'affinity_creators'));
        $this->appendCandidates($selected, $this->interestCandidates($user, $onset, $userTopics, $this->mergeExcludedIds($this->selectedIds($selected), $baselineExcludeIds), (int) ceil($limit * 0.22), FeedService::TYPE_REELS, 'topic_match'));
        $this->appendCandidates($selected, $this->highRetentionCandidates($user, $onset, $this->mergeExcludedIds($this->selectedIds($selected), $baselineExcludeIds), (int) ceil($limit * 0.22), 'high_retention_unseen'));
        $this->appendCandidates($selected, $this->followedCandidates($user, $onset, $followedAuthorIds, $this->mergeExcludedIds($this->selectedIds($selected), $baselineExcludeIds), (int) ceil($limit * 0.14), FeedService::TYPE_REELS, 'followed_creators'));
        $this->appendCandidates($selected, $this->explorationCandidates($user, $onset, $this->mergeExcludedIds($this->selectedIds($selected), $baselineExcludeIds), (int) ceil($limit * 0.10), FeedService::TYPE_REELS, 'exploration'));
        $this->appendCandidates($selected, $this->oldGoodCandidates($user, $onset, $this->mergeExcludedIds($this->selectedIds($selected), $baselineExcludeIds), $limit - $selected->count(), FeedService::TYPE_REELS, 'recovery_diversity'));

        if($selected->count() < $limit) {
            $this->appendCandidates($selected, $this->recentCandidates($user, $onset, $this->mergeExcludedIds($this->selectedIds($selected), $feedbackExcludeIds), $limit - $selected->count(), FeedService::TYPE_REELS, 'fresh_exploration'));
        }

        return $selected->unique('id')->take($limit)->values();
    }

    private function followedCandidates(User $user, int $onset, array $followedAuthorIds, array $excludeIds, int $limit, string $type, string $source = 'followed'): Collection
    {
        if(empty($followedAuthorIds) || $limit <= 0) {
            return collect();
        }

        $query = $this->baseTimelineQuery($user, $onset, $type)
            ->whereIn('user_id', $followedAuthorIds);

        $this->excludeSelected($query, $excludeIds);

        return $this->tagCandidateSource(
            $this->orderByRecencyAndEngagement($query, $type)->limit($limit)->get(),
            $source
        );
    }

    private function interestCandidates(User $user, int $onset, array $topics, array $excludeIds, int $limit, string $type, string $source = 'interest'): Collection
    {
        if(empty($topics) || $limit <= 0) {
            return collect();
        }

        $query = $this->baseTimelineQuery($user, $onset, $type)
            ->whereHas('topics', function($query) use ($topics) {
                $query->whereIn('topic', $topics);
            });

        $this->excludeSelected($query, $excludeIds);

        return $this->tagCandidateSource(
            $this->orderByRecencyAndEngagement($query, $type)->limit($limit)->get(),
            $source
        );
    }

    private function recentCandidates(User $user, int $onset, array $excludeIds, int $limit, string $type, string $source = 'recent'): Collection
    {
        if($limit <= 0) {
            return collect();
        }

        $query = $this->baseTimelineQuery($user, $onset, $type);
        $this->excludeSelected($query, $excludeIds);

        return $this->tagCandidateSource(
            $this->orderByRecencyAndEngagement($query, $type)->limit($limit)->get(),
            $source
        );
    }

    private function oldGoodCandidates(User $user, int $onset, array $excludeIds, int $limit, string $type, string $source = 'old_good'): Collection
    {
        if($limit <= 0) {
            return collect();
        }

        $query = $this->baseTimelineQuery($user, $onset, $type)
            ->where('created_at', '<=', now()->subDay());

        $this->excludeSelected($query, $excludeIds);

        return $this->tagCandidateSource(
            $this->orderByEngagement($query, $type)->limit($limit)->get(),
            $source
        );
    }

    private function popularCandidates(User $user, int $onset, array $excludeIds, int $limit, string $type, string $source): Collection
    {
        if($limit <= 0) {
            return collect();
        }

        $query = $this->baseTimelineQuery($user, $onset, $type)
            ->where('created_at', '>=', now()->subDays(10));

        $this->excludeSelected($query, $excludeIds);

        return $this->tagCandidateSource(
            $this->orderByEngagement($query, $type)->limit($limit)->get(),
            $source
        );
    }

    private function explorationCandidates(User $user, int $onset, array $excludeIds, int $limit, string $type, string $source): Collection
    {
        if($limit <= 0) {
            return collect();
        }

        $query = $this->baseTimelineQuery($user, $onset, $type)
            ->where('created_at', '>=', now()->subDays(7));

        $this->excludeSelected($query, $excludeIds);

        return $this->tagCandidateSource(
            $this->orderByRecencyAndEngagement($query, $type)->limit($limit)->get(),
            $source
        );
    }

    private function authorAffinityCandidates(User $user, int $onset, array $authorIds, array $excludeIds, int $limit, string $type, string $source): Collection
    {
        if(empty($authorIds) || $limit <= 0) {
            return collect();
        }

        $query = $this->baseTimelineQuery($user, $onset, $type)
            ->whereIn('user_id', $authorIds);

        $this->excludeSelected($query, $excludeIds);

        return $this->tagCandidateSource(
            $this->orderByRecencyAndEngagement($query, $type)->limit($limit)->get(),
            $source
        );
    }

    private function highRetentionCandidates(User $user, int $onset, array $excludeIds, int $limit, string $source): Collection
    {
        if($limit <= 0) {
            return collect();
        }

        $query = $this->baseTimelineQuery($user, $onset, FeedService::TYPE_REELS)
            ->where('created_at', '>=', now()->subDays(14));

        $this->excludeSelected($query, $excludeIds);

        return $this->tagCandidateSource(
            $this->orderByEngagement($query, FeedService::TYPE_REELS)->limit($limit)->get(),
            $source
        );
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

    private function mergeExcludedIds(array $left, array $right): array
    {
        return collect($left)
            ->merge($right)
            ->map(fn($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
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
            ->where('topic', 'not like', '%:%')
            ->where('score', '>', 0)
            ->orderByDesc('score')
            ->limit(12)
            ->pluck('topic')
            ->all();
    }

    private function interactedAuthorIds(User $user, int $limit = 18): array
    {
        $authorIds = collect($this->userInterestService->topPositiveAffinityValues($user, 'author:', $limit))
            ->map(fn($authorId) => (int) $authorId)
            ->filter()
            ->unique()
            ->values();

        if($authorIds->isNotEmpty()) {
            return $authorIds->all();
        }

        return DB::table(Table::FEED_EVENTS)
            ->join(Table::POSTS, Table::POSTS . '.id', '=', Table::FEED_EVENTS . '.post_id')
            ->where(Table::FEED_EVENTS . '.user_id', $user->id)
            ->whereIn(Table::FEED_EVENTS . '.event_type', FeedTelemetryService::SEEN_EVENT_TYPES)
            ->where(Table::FEED_EVENTS . '.created_at', '>=', now()->subDays(45))
            ->groupBy(Table::POSTS . '.user_id')
            ->orderByRaw('COUNT(*) DESC')
            ->limit($limit)
            ->pluck(Table::POSTS . '.user_id')
            ->map(fn($authorId) => (int) $authorId)
            ->all();
    }

    private function recentlySeenReelIds(User $user): array
    {
        return DB::table(Table::FEED_EVENTS)
            ->select('post_id', DB::raw('MAX(created_at) as last_seen_at'))
            ->where('user_id', $user->id)
            ->whereNotNull('post_id')
            ->whereIn('event_type', FeedTelemetryService::SEEN_EVENT_TYPES)
            ->where('created_at', '>=', now()->subDays(7))
            ->groupBy('post_id')
            ->orderByDesc('last_seen_at')
            ->limit(600)
            ->pluck('post_id')
            ->map(fn($id) => (int) $id)
            ->all();
    }

    private function feedbackSuppressedReelIds(User $user): array
    {
        return DB::table(Table::FEED_EVENTS)
            ->select('post_id', DB::raw('MAX(created_at) as last_feedback_at'))
            ->where('user_id', $user->id)
            ->whereNotNull('post_id')
            ->whereIn('event_type', FeedTelemetryService::FEEDBACK_EVENT_TYPES)
            ->where('created_at', '>=', now()->subDays(120))
            ->groupBy('post_id')
            ->orderByDesc('last_feedback_at')
            ->limit(1000)
            ->pluck('post_id')
            ->map(fn($id) => (int) $id)
            ->all();
    }

    private function orderByRecencyAndEngagement(Builder $query, string $type = ''): Builder
    {
        if($type === FeedService::TYPE_REELS) {
            $this->orderByReelsWatchQuality($query);
        }

        return $query
            ->orderBy('created_at', 'desc')
            ->orderBy('comments_count', 'desc')
            ->orderBy('bookmarks_count', 'desc')
            ->orderBy('shares_count', 'desc')
            ->orderBy('views_count', 'desc')
            ->orderBy('id', 'desc');
    }

    private function orderByEngagement(Builder $query, string $type = ''): Builder
    {
        if($type === FeedService::TYPE_REELS) {
            $this->orderByReelsWatchQuality($query);
        }

        return $query
            ->orderBy('comments_count', 'desc')
            ->orderBy('bookmarks_count', 'desc')
            ->orderBy('shares_count', 'desc')
            ->orderBy('views_count', 'desc')
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc');
    }

    private function orderByReelsWatchQuality(Builder $query): Builder
    {
        return $query
            ->orderByDesc(PostVideoMetric::query()
                ->select('intelligence_score')
                ->whereColumn(Table::POST_VIDEO_METRICS . '.post_id', Table::POSTS . '.id')
                ->limit(1))
            ->orderByDesc(PostVideoMetric::query()
                ->select('avg_completion_rate')
                ->whereColumn(Table::POST_VIDEO_METRICS . '.post_id', Table::POSTS . '.id')
                ->limit(1))
            ->orderByDesc(PostVideoMetric::query()
                ->select('rewatch_rate')
                ->whereColumn(Table::POST_VIDEO_METRICS . '.post_id', Table::POSTS . '.id')
                ->limit(1));
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
                'followed' => 0.30,
                'interest' => 0.25,
                'recent' => 0.15,
            ];
        }

        return [
            'followed' => 0.65,
            'interest' => 0.15,
            'recent' => 0.10,
        ];
    }

    private function tagCandidateSource(Collection $posts, string $source): Collection
    {
        return $posts->map(function(Post $post) use ($source) {
            $post->setAttribute('candidate_source', $source);

            return $post;
        });
    }

    private function hasWarmSignals(array $followedAuthorIds, array $interactedAuthorIds, array $userTopics): bool
    {
        return ! empty($followedAuthorIds) || ! empty($interactedAuthorIds) || ! empty($userTopics);
    }

    private function isHomeRankingV2Enabled(): bool
    {
        return (bool) config('features.feed_ranking_v2.enabled', false);
    }

    private function isReelsRankingV2Enabled(): bool
    {
        return (bool) config('features.reels_ranking_v2.enabled', false);
    }
}
