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
        private PostAffinityService $postAffinityService,
        private CreatorQualityService $creatorQualityService,
        private ReelQualityService $reelQualityService,
        private SafetyService $safetyService
    ) {
    }

    public function rankVersion(string $type): string
    {
        if($type === FeedService::TYPE_REELS && $this->isReelsRankingV2Enabled()) {
            return 'reels_ranking_v2';
        }

        if($type === FeedService::TYPE_FOR_YOU && $this->isHomeRankingV2Enabled()) {
            return 'home_ranking_v2';
        }

        return 'candidate_ranking_v1';
    }

    public function feedFamily(string $type): string
    {
        return match($type) {
            FeedService::TYPE_REELS => 'reels',
            FeedService::TYPE_FOLLOWING => 'following',
            FeedService::TYPE_LATEST => 'latest',
            default => 'home',
        };
    }

    public function reRankAllowed(string $type): bool
    {
        return in_array($type, [FeedService::TYPE_FOR_YOU, FeedService::TYPE_REELS], true)
            && $this->rankVersion($type) !== 'candidate_ranking_v1';
    }

    public function sessionWindowSize(string $type): int
    {
        return match($type) {
            FeedService::TYPE_REELS => 50,
            FeedService::TYPE_FOR_YOU => 50,
            default => 0,
        };
    }

    public function rank(User $user, Collection $candidates, string $type, array $filter = []): Collection
    {
        if($candidates->isEmpty()) {
            return collect();
        }

        $context = $this->buildContext($user, $candidates, $filter);

        $ranked = $candidates
            ->map(function(Post $post) use ($user, $type, $context) {
                return $this->attachRanking($post, $this->scorePost($user, $post, $type, $context), $type);
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
            'author_quality' => $this->authorQualityScore($post, $context),
            'media_bonus' => $this->mediaBonus($post),
            'video_intelligence' => $this->videoIntelligenceScore($post),
            'retention_quality' => $this->retentionQualityScore($post, $context),
            'interest' => $this->interestScore($post, $context),
            'affinity_author' => $this->affinityScore($post, $context, 'author:'),
            'affinity_language' => $this->affinityScore($post, $context, 'language:'),
            'affinity_media_type' => $this->affinityScore($post, $context, 'media_type:'),
            'affinity_duration' => $this->affinityScore($post, $context, 'duration_bucket:'),
            'affinity_sound' => $this->affinityScore($post, $context, 'sound_signature:'),
            'candidate_source_boost' => $this->candidateSourceBoost($post, $type),
            'seen_penalty' => $this->seenPenalty($post, $type, $context),
            'session_jitter' => $this->sessionJitter($user, $post, $type, $context),
            'feedback_penalty' => -$this->feedbackPenalty($post, $type, $context),
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
            'reasons' => $this->topReasonCodes($signals),
        ];
    }

    private function buildContext(User $user, Collection $candidates, array $filter): array
    {
        $candidateIds = $candidates->pluck('id')->all();
        $candidateAuthors = $candidates->pluck('user_id', 'id');
        $candidateAuthorIds = $candidates->pluck('user_id')->map(fn($id) => (int) $id)->unique()->values()->all();
        $candidateTopics = $this->candidateTopics($candidates);
        $candidateAffinityKeys = $this->postAffinityService->affinityKeysForPosts($candidates, [
            'author:',
            'language:',
            'media_type:',
            'duration_bucket:',
            'sound_signature:',
        ]);
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
            'affinity_scores' => $this->userInterestService->scoresForAffinityKeys($user, $candidateAffinityKeys),
            'post_affinity_keys' => $candidates->mapWithKeys(function(Post $post) {
                return [$post->id => array_keys($this->postAffinityService->weightedKeysForPost($post))];
            })->all(),
            'creator_quality_scores' => $this->creatorQualityService->scoresForAuthors($candidateAuthorIds),
            'reel_quality_scores' => $this->reelQualityService->scoresForPosts($candidateIds),
            'seen_stats_by_post' => $seenStatsByPost,
            'feedback_stats_by_post' => $this->feedbackStatsByPost($user, $candidateIds),
            'feedback_author_penalty_by_author' => $this->feedbackAuthorPenaltyByAuthor($user, $candidateAuthorIds),
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

    private function authorQualityScore(Post $post, array $context): float
    {
        return (float) data_get($context, "creator_quality_scores.{$post->user_id}", 0.0);
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

    private function affinityScore(Post $post, array $context, string $prefix): float
    {
        $keys = collect(data_get($context, "post_affinity_keys.{$post->id}", []))
            ->filter(fn($key) => str_starts_with((string) $key, $prefix))
            ->values();

        if($keys->isEmpty()) {
            return 0.0;
        }

        $score = $keys->sum(function(string $key) use ($context) {
            return (float) data_get($context, "affinity_scores.{$key}", 0.0);
        });

        return max(-40.0, min(45.0, $score));
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

    private function feedbackPenalty(Post $post, string $type, array $context): float
    {
        $postFeedback = data_get($context, "feedback_stats_by_post.{$post->id}", []);
        $postPenalty = match(data_get($postFeedback, 'last_event_type')) {
            FeedTelemetryService::EVENT_POST_HIDE => 340.0,
            FeedTelemetryService::EVENT_POST_NOT_INTERESTED => 220.0,
            default => 0.0,
        };

        $feedbackCount = max(0, (int) data_get($postFeedback, 'feedback_count', 0) - 1);
        $postPenalty += $feedbackCount * 45.0;

        if($type !== FeedService::TYPE_REELS) {
            return min(480.0, $postPenalty);
        }

        $authorPenalty = (float) data_get($context, "feedback_author_penalty_by_author.{$post->user_id}", 0.0);

        return min(560.0, $postPenalty + $authorPenalty);
    }

    private function videoIntelligenceScore(Post $post): float
    {
        if(! $post->type->isVideo() || empty($post->videoMetric)) {
            return 0.0;
        }

        return max(-45, min(65, (float) $post->videoMetric->intelligence_score));
    }

    private function retentionQualityScore(Post $post, array $context): float
    {
        return (float) data_get($context, "reel_quality_scores.{$post->id}", $this->videoIntelligenceScore($post));
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
        $seenTopics = [];

        return $ranked->map(function(Post $post) use (&$seenAuthors, &$seenTopics, $type) {
            $seenCount = $seenAuthors[$post->user_id] ?? 0;
            $penalty = $this->authorRepetitionPenalty($seenCount, $type);
            $seenAuthors[$post->user_id] = $seenCount + 1;

            if($type === FeedService::TYPE_REELS) {
                foreach($this->postTopics($post) as $topic) {
                    $topicSeenCount = $seenTopics[$topic] ?? 0;

                    if($topicSeenCount >= 4) {
                        $penalty += min(260.0, 145.0 + (($topicSeenCount - 4) * 40.0));
                    }

                    $seenTopics[$topic] = $topicSeenCount + 1;
                }
            }

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
                $seenCount === 1 => 120.0,
                $seenCount === 2 => 320.0,
                default => min(520.0, 360.0 + (($seenCount - 3) * 110.0)),
            };
        }

        return $seenCount * 12.0;
    }

    private function weightsForType(string $type): array
    {
        if($type === FeedService::TYPE_REELS && $this->isReelsRankingV2Enabled()) {
            return [
                'freshness' => 0.45,
                'engagement' => 0.35,
                'relationship' => 0.30,
                'author_quality' => 0.90,
                'media_bonus' => 0.05,
                'video_intelligence' => 0.20,
                'retention_quality' => 1.75,
                'interest' => 0.65,
                'affinity_author' => 1.20,
                'affinity_language' => 0.45,
                'affinity_media_type' => 0.40,
                'affinity_duration' => 0.55,
                'affinity_sound' => 0.55,
                'candidate_source_boost' => 1.00,
                'seen_penalty' => 1.55,
                'session_jitter' => 0.25,
                'feedback_penalty' => 1.45,
                'report_penalty' => 1.25,
                'safety_penalty' => 1.25,
                'repetition_penalty' => 1.0,
            ];
        }

        if($type === FeedService::TYPE_FOR_YOU && $this->isHomeRankingV2Enabled()) {
            return [
                'freshness' => 0.95,
                'engagement' => 0.80,
                'relationship' => 1.15,
                'author_quality' => 0.70,
                'media_bonus' => 0.45,
                'video_intelligence' => 0.20,
                'retention_quality' => 0.30,
                'interest' => 0.85,
                'affinity_author' => 1.15,
                'affinity_language' => 0.35,
                'affinity_media_type' => 0.30,
                'affinity_duration' => 0.30,
                'affinity_sound' => 0.20,
                'candidate_source_boost' => 0.90,
                'seen_penalty' => 1.10,
                'session_jitter' => 0.35,
                'feedback_penalty' => 1.10,
                'report_penalty' => 1.0,
                'safety_penalty' => 1.0,
                'repetition_penalty' => 1.0,
            ];
        }

        if($type === FeedService::TYPE_REELS) {
            return [
                'freshness' => 0.55,
                'engagement' => 0.65,
                'relationship' => 0.55,
                'author_quality' => 0.45,
                'media_bonus' => 0.10,
                'video_intelligence' => 1.90,
                'retention_quality' => 0.0,
                'interest' => 1.25,
                'affinity_author' => 0.0,
                'affinity_language' => 0.0,
                'affinity_media_type' => 0.0,
                'affinity_duration' => 0.0,
                'affinity_sound' => 0.0,
                'candidate_source_boost' => 0.0,
                'seen_penalty' => 1.45,
                'session_jitter' => 0.55,
                'feedback_penalty' => 1.35,
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
                'retention_quality' => 0.0,
                'interest' => 0.25,
                'affinity_author' => 0.0,
                'affinity_language' => 0.0,
                'affinity_media_type' => 0.0,
                'affinity_duration' => 0.0,
                'affinity_sound' => 0.0,
                'candidate_source_boost' => 0.0,
                'seen_penalty' => 1.0,
                'session_jitter' => 0.45,
                'feedback_penalty' => 1.1,
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
            'retention_quality' => 0.0,
            'interest' => 1.0,
            'affinity_author' => 0.0,
            'affinity_language' => 0.0,
            'affinity_media_type' => 0.0,
            'affinity_duration' => 0.0,
            'affinity_sound' => 0.0,
            'candidate_source_boost' => 0.0,
            'seen_penalty' => 1.0,
            'session_jitter' => 1.0,
            'feedback_penalty' => 1.0,
            'report_penalty' => 1.0,
            'safety_penalty' => 1.0,
            'repetition_penalty' => 1.0,
        ];
    }

    private function attachRanking(Post $post, array $ranking, string $type): Post
    {
        $post->setAttribute('ranking_score', $ranking['score']);
        $post->setAttribute('ranking_signals', $ranking['signals']);
        $post->setAttribute('ranking_version', $this->rankVersion($type));
        $post->setAttribute('ranking_reasons', $ranking['reasons']);
        $post->setAttribute('candidate_source', $post->candidate_source ?? null);

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

    private function candidateSourceBoost(Post $post, string $type): float
    {
        $source = (string) ($post->candidate_source ?? '');

        if($source === '') {
            return 0.0;
        }

        $weights = [
            FeedService::TYPE_FOR_YOU => [
                'followed' => 14.0,
                'interacted_author' => 12.0,
                'topic_match' => 8.0,
                'fresh_exploration' => 5.0,
                'old_good_recovery' => 4.0,
                'recent_safe' => 7.0,
                'popular_general' => 9.0,
                'exploration' => 4.0,
            ],
            FeedService::TYPE_REELS => [
                'affinity_creators' => 12.0,
                'high_retention_unseen' => 11.0,
                'followed_creators' => 6.0,
                'exploration' => 5.0,
                'recovery_diversity' => 4.0,
            ],
        ];

        return (float) data_get($weights, "{$type}.{$source}", 0.0);
    }

    private function topReasonCodes(array $signals): array
    {
        return collect($signals)
            ->reject(function($value, $signal) {
                return in_array($signal, ['session_jitter', 'repetition_penalty'], true) || $value <= 0;
            })
            ->sortDesc()
            ->keys()
            ->take(3)
            ->values()
            ->all();
    }

    private function isHomeRankingV2Enabled(): bool
    {
        return (bool) config('features.feed_ranking_v2.enabled', false);
    }

    private function isReelsRankingV2Enabled(): bool
    {
        return (bool) config('features.reels_ranking_v2.enabled', false);
    }

    private function feedbackStatsByPost(User $user, array $candidateIds): array
    {
        if(empty($candidateIds)) {
            return [];
        }

        return DB::table(Table::FEED_EVENTS)
            ->select('post_id', 'event_type', 'created_at')
            ->where('user_id', $user->id)
            ->whereIn('post_id', $candidateIds)
            ->whereIn('event_type', FeedTelemetryService::FEEDBACK_EVENT_TYPES)
            ->where('created_at', '>=', now()->subDays(120))
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('post_id')
            ->mapWithKeys(function(Collection $events, int|string $postId) {
                $latest = $events->first();

                return [
                    (int) $postId => [
                        'last_event_type' => $latest->event_type,
                        'last_feedback_at' => $latest->created_at,
                        'feedback_count' => $events->count(),
                    ],
                ];
            })
            ->all();
    }

    private function feedbackAuthorPenaltyByAuthor(User $user, array $candidateAuthorIds): array
    {
        if(empty($candidateAuthorIds)) {
            return [];
        }

        return DB::table(Table::FEED_EVENTS)
            ->join(Table::POSTS, Table::POSTS . '.id', '=', Table::FEED_EVENTS . '.post_id')
            ->select(
                Table::POSTS . '.user_id as author_id',
                DB::raw('SUM(CASE
                    WHEN ' . Table::FEED_EVENTS . ".event_type = '" . FeedTelemetryService::EVENT_POST_HIDE . "' THEN 26
                    WHEN " . Table::FEED_EVENTS . ".event_type = '" . FeedTelemetryService::EVENT_POST_NOT_INTERESTED . "' THEN 12
                    ELSE 0
                END) as feedback_penalty")
            )
            ->where(Table::FEED_EVENTS . '.user_id', $user->id)
            ->whereIn(Table::POSTS . '.user_id', $candidateAuthorIds)
            ->where(Table::POSTS . '.type', PostType::VIDEO->value)
            ->whereIn(Table::FEED_EVENTS . '.event_type', FeedTelemetryService::FEEDBACK_EVENT_TYPES)
            ->where(Table::FEED_EVENTS . '.created_at', '>=', now()->subDays(120))
            ->groupBy(Table::POSTS . '.user_id')
            ->get()
            ->mapWithKeys(fn($row) => [
                (int) $row->author_id => min(160.0, (float) $row->feedback_penalty),
            ])
            ->all();
    }
}
