<?php

namespace App\Services\Timeline;

use App\Database\Configs\Table;
use App\Enums\Post\PostType;
use App\Models\Post;
use App\Models\User;
use App\Services\Safety\SafetyService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FeedRankingService
{
    public function __construct(
        private TopicExtractionService $topicExtractionService,
        private UserInterestService $userInterestService,
        private SafetyService $safetyService
    ) {
    }

    public function rank(User $user, Collection $candidates, string $type, array $filter = []): Collection
    {
        if($candidates->isEmpty()) {
            return collect();
        }

        $context = $this->buildContext($user, $candidates, $filter);

        $ranked = $candidates
            ->map(function(Post $post) use ($user, $type, $context) {
                return $this->attachRanking($post, $this->scorePost($user, $post, $type, $context));
            });

        $ranked = $this->sortRankedPosts($ranked);

        return $this->sortRankedPosts($this->applyRepetitionPenalty($ranked, $type));
    }

    private function scorePost(User $user, Post $post, string $type, array $context): array
    {
        $signals = [
            'freshness' => $this->freshnessScore($post),
            'engagement' => $this->engagementScore($post),
            'relationship' => $this->relationshipScore($user, $post, $context),
            'author_quality' => $this->authorQualityScore($post),
            'media_bonus' => $this->mediaBonus($post),
            'video_intelligence' => $this->videoIntelligenceScore($post),
            'interest' => $this->interestScore($post, $context),
            'seen_penalty' => $this->seenPenalty($post, $type, $context),
            'session_jitter' => $this->sessionJitter($user, $post, $type, $context),
            'report_penalty' => -$this->reportPenalty($post),
            'safety_penalty' => -$this->safetyService->authorSafetyPenalty($post),
            'repetition_penalty' => 0.0,
        ];

        $weights = $this->weightsForType($type);
        $score = collect($signals)->reduce(function(float $carry, float $signalValue, string $signal) use ($weights) {
            return $carry + ($signalValue * data_get($weights, $signal, 1.0));
        }, 0.0);

        return [
            'score' => round($score, 4),
            'signals' => array_map(fn($value) => round($value, 4), $signals),
        ];
    }

    private function buildContext(User $user, Collection $candidates, array $filter): array
    {
        $candidateIds = $candidates->pluck('id')->all();
        $candidateAuthors = $candidates->pluck('user_id', 'id');
        $candidateTopics = $this->candidateTopics($candidates);
        $sessionId = trim((string) data_get($filter, 'session_id', ''));

        $commentedPostIds = DB::table(Table::COMMENTS)
            ->where('user_id', $user->id)
            ->whereIn('post_id', $candidateIds)
            ->pluck('post_id')
            ->all();

        $bookmarkedPostIds = DB::table(Table::BOOKMARKS)
            ->where('user_id', $user->id)
            ->where('bookmarkable_type', Post::class)
            ->whereIn('bookmarkable_id', $candidateIds)
            ->pluck('bookmarkable_id')
            ->all();

        $reactedPostIds = $candidates->filter(function(Post $post) use ($user) {
            return $post->reactions->contains(function($reaction) use ($user) {
                return collect($reaction->users)->map(fn($userId) => (int) $userId)->contains($user->id);
            });
        })->pluck('id')->all();

        $interactedPostIds = collect($commentedPostIds)
            ->merge($bookmarkedPostIds)
            ->merge($reactedPostIds)
            ->unique()
            ->values();

        $interactedAuthorIds = $interactedPostIds
            ->map(fn($postId) => $candidateAuthors->get($postId))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $followedAuthorIds = DB::table(Table::FOLLOWS)
            ->where('follower_id', $user->id)
            ->where('status', \App\Enums\User\FollowStatus::FOLLOWING->value)
            ->pluck('following_id')
            ->all();

        $seenStatsByPost = $this->seenStatsByPost($user, $candidateIds, $sessionId);

        return [
            'session_id' => $sessionId,
            'commented_post_ids' => array_map('intval', $commentedPostIds),
            'bookmarked_post_ids' => array_map('intval', $bookmarkedPostIds),
            'reacted_post_ids' => array_map('intval', $reactedPostIds),
            'interacted_post_ids' => $interactedPostIds->map(fn($postId) => (int) $postId)->all(),
            'interacted_author_ids' => array_map('intval', $interactedAuthorIds),
            'followed_author_ids' => array_map('intval', $followedAuthorIds),
            'interest_scores' => $this->userInterestService->scoresForTopics($user, $candidateTopics),
            'seen_stats_by_post' => $seenStatsByPost,
        ];
    }

    private function freshnessScore(Post $post): float
    {
        $createdAt = Carbon::parse($post->getRawOriginal('created_at'));
        $ageHours = max(0.0, $createdAt->diffInMinutes(now()) / 60);

        return 45 / (1 + ($ageHours / 24));
    }

    private function engagementScore(Post $post): float
    {
        $rawEngagement = ((int) $post->comments_count * 3)
            + ((int) $post->shares_count * 5)
            + ((int) $post->bookmarks_count * 4)
            + ((int) $post->quotes_count * 3)
            + ((int) $post->views_count * 0.05)
            + ($this->reactionCount($post) * 2);

        return min(45, log($rawEngagement + 1) * 8);
    }

    private function relationshipScore(User $user, Post $post, array $context): float
    {
        $score = 0.0;

        if($post->user_id === $user->id) {
            $score += 6;
        }

        if(in_array((int) $post->user_id, $context['followed_author_ids'], true)) {
            $score += 28;
        }

        if(in_array((int) $post->user_id, $context['interacted_author_ids'], true)) {
            $score += 18;
        }

        if(in_array((int) $post->id, $context['interacted_post_ids'], true)) {
            $score += 26;
        }

        return min(55, $score);
    }

    private function authorQualityScore(Post $post): float
    {
        $author = $post->user;

        if(empty($author)) {
            return 0.0;
        }

        $score = ($author->verified ? 8 : 0)
            + (log(((int) $author->followers_count) + 1) * 2.5)
            + (log(((int) $author->publications_count) + 1) * 1.4);

        return min(22, $score);
    }

    private function mediaBonus(Post $post): float
    {
        return match($post->type) {
            PostType::VIDEO => 10.0,
            PostType::IMAGE, PostType::GIF => 6.0,
            PostType::POLL => 5.0,
            PostType::AUDIO, PostType::DOCUMENT => 3.0,
            default => 0.0,
        };
    }

    private function interestScore(Post $post, array $context): float
    {
        $topics = $this->postTopics($post);

        if(empty($topics)) {
            return 0.0;
        }

        $score = collect($topics)->sum(function(string $topic) use ($context) {
            return (float) data_get($context['interest_scores'], $topic, 0);
        });

        return max(-35, min(45, $score));
    }

    private function seenPenalty(Post $post, string $type, array $context): float
    {
        $seenStats = data_get($context['seen_stats_by_post'], $post->id, []);
        $lastSeenAt = data_get($seenStats, 'last_seen_at');

        if(empty($lastSeenAt)) {
            return 0.0;
        }

        $minutesSinceSeen = max(0, Carbon::parse($lastSeenAt)->diffInMinutes(now()));
        $seenCount = max(1, (int) data_get($seenStats, 'seen_count', 1));

        $basePenalty = $type === FeedService::TYPE_REELS
            ? $this->reelsSeenPenalty($minutesSinceSeen)
            : $this->feedSeenPenalty($minutesSinceSeen);

        $repeatPenalty = max(0, $seenCount - 1) * ($type === FeedService::TYPE_REELS ? -100.0 : -35.0);
        $minimumPenalty = $type === FeedService::TYPE_REELS ? -360.0 : -180.0;

        return max($minimumPenalty, $basePenalty + $repeatPenalty);
    }

    private function reelsSeenPenalty(int $minutesSinceSeen): float
    {
        return match(true) {
            $minutesSinceSeen < 15 => -240.0,
            $minutesSinceSeen < 360 => -210.0,
            $minutesSinceSeen < 1440 => -180.0,
            $minutesSinceSeen < 10080 => -130.0,
            $minutesSinceSeen < 43200 => -70.0,
            default => -28.0,
        };
    }

    private function feedSeenPenalty(int $minutesSinceSeen): float
    {
        return match(true) {
            $minutesSinceSeen < 15 => -120.0,
            $minutesSinceSeen < 360 => -95.0,
            $minutesSinceSeen < 1440 => -75.0,
            $minutesSinceSeen < 10080 => -45.0,
            $minutesSinceSeen < 43200 => -18.0,
            default => -8.0,
        };
    }

    private function sessionJitter(User $user, Post $post, string $type, array $context): float
    {
        $sessionId = data_get($context, 'session_id');

        if(empty($sessionId)) {
            return 0.0;
        }

        $maxBoost = $type === FeedService::TYPE_FOLLOWING ? 2.0 : 6.0;
        $hash = (int) sprintf('%u', crc32("{$user->id}:{$post->id}:{$sessionId}:{$type}"));

        return round(($hash / 4294967295) * $maxBoost, 4);
    }

    private function videoIntelligenceScore(Post $post): float
    {
        if(! $post->type->isVideo() || empty($post->videoMetric)) {
            return 0.0;
        }

        return max(-45, min(65, (float) $post->videoMetric->intelligence_score));
    }

    private function reportPenalty(Post $post): float
    {
        return min(70, ((int) ($post->reports_count ?? 0)) * 24);
    }

    private function reactionCount(Post $post): int
    {
        return (int) $post->reactions->sum('reactions_count');
    }

    private function applyRepetitionPenalty(Collection $ranked, string $type): Collection
    {
        if($type === FeedService::TYPE_FOLLOWING) {
            return $ranked;
        }

        $seenAuthors = [];

        return $ranked->map(function(Post $post) use (&$seenAuthors, $type) {
            $seenCount = $seenAuthors[$post->user_id] ?? 0;
            $penalty = $this->authorRepetitionPenalty($seenCount, $type);
            $seenAuthors[$post->user_id] = $seenCount + 1;

            if($penalty > 0) {
                $signals = $post->ranking_signals;
                $signals['repetition_penalty'] = -$penalty;

                $post->setAttribute('ranking_signals', $signals);
                $post->setAttribute('ranking_score', round($post->ranking_score - $penalty, 4));
            }

            return $post;
        });
    }

    private function authorRepetitionPenalty(int $seenCount, string $type): float
    {
        if($seenCount <= 0) {
            return 0.0;
        }

        if($type === FeedService::TYPE_REELS) {
            return match(true) {
                $seenCount === 1 => 28.0,
                $seenCount === 2 => 72.0,
                default => min(320.0, 150.0 + (($seenCount - 3) * 85.0)),
            };
        }

        return $seenCount * 12.0;
    }

    private function weightsForType(string $type): array
    {
        if($type === FeedService::TYPE_REELS) {
            return [
                'freshness' => 0.55,
                'engagement' => 0.65,
                'relationship' => 0.55,
                'author_quality' => 0.45,
                'media_bonus' => 0.10,
                'video_intelligence' => 1.90,
                'interest' => 1.25,
                'seen_penalty' => 1.45,
                'session_jitter' => 0.55,
                'report_penalty' => 1.20,
                'safety_penalty' => 1.20,
                'repetition_penalty' => 1.0,
            ];
        }

        if($type === FeedService::TYPE_FOLLOWING) {
            return [
                'freshness' => 1.45,
                'engagement' => 0.45,
                'relationship' => 1.1,
                'author_quality' => 0.25,
                'media_bonus' => 0.2,
                'video_intelligence' => 0.25,
                'interest' => 0.25,
                'seen_penalty' => 1.0,
                'session_jitter' => 0.45,
                'report_penalty' => 1.0,
                'safety_penalty' => 1.0,
                'repetition_penalty' => 1.0,
            ];
        }

        return [
            'freshness' => 1.0,
            'engagement' => 1.0,
            'relationship' => 1.0,
            'author_quality' => 0.7,
            'media_bonus' => 0.7,
            'video_intelligence' => 0.9,
            'interest' => 1.0,
            'seen_penalty' => 1.0,
            'session_jitter' => 1.0,
            'report_penalty' => 1.0,
            'safety_penalty' => 1.0,
            'repetition_penalty' => 1.0,
        ];
    }

    private function attachRanking(Post $post, array $ranking): Post
    {
        $post->setAttribute('ranking_score', $ranking['score']);
        $post->setAttribute('ranking_signals', $ranking['signals']);

        return $post;
    }

    private function sortRankedPosts(Collection $posts): Collection
    {
        return $posts->sort(function(Post $left, Post $right) {
            return [
                (float) $right->ranking_score,
                Carbon::parse($right->getRawOriginal('created_at'))->timestamp,
                (int) $right->id,
            ] <=> [
                (float) $left->ranking_score,
                Carbon::parse($left->getRawOriginal('created_at'))->timestamp,
                (int) $left->id,
            ];
        })->values();
    }

    private function candidateTopics(Collection $candidates): array
    {
        return $candidates
            ->flatMap(fn(Post $post) => $this->postTopics($post))
            ->unique()
            ->values()
            ->all();
    }

    private function postTopics(Post $post): array
    {
        $topics = $this->topicExtractionService->ensurePostTopics($post);

        if($topics->isEmpty()) {
            return [];
        }

        return $topics->pluck('topic')->all();
    }

    private function seenStatsByPost(User $user, array $candidateIds, string $sessionId): array
    {
        if(empty($candidateIds)) {
            return [];
        }

        return DB::table(Table::FEED_EVENTS)
            ->select('post_id', DB::raw('MAX(created_at) as last_seen_at'), DB::raw('COUNT(*) as seen_count'))
            ->where('user_id', $user->id)
            ->whereIn('post_id', $candidateIds)
            ->whereIn('event_type', FeedTelemetryService::SEEN_EVENT_TYPES)
            ->where('created_at', '>=', now()->subDays(30))
            ->when($sessionId !== '', function($query) use ($sessionId) {
                $query->where(function($query) use ($sessionId) {
                    $query->whereNull('session_id')
                        ->orWhere('session_id', '!=', $sessionId);
                });
            })
            ->groupBy('post_id')
            ->get()
            ->mapWithKeys(fn($stats) => [
                (int) $stats->post_id => [
                    'last_seen_at' => $stats->last_seen_at,
                    'seen_count' => (int) $stats->seen_count,
                ],
            ])
            ->all();
    }
}
