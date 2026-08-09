<?php

namespace App\Services\Timeline;

use App\Database\Configs\Table;
use App\Enums\Post\PostStatus;
use App\Enums\User\FollowStatus;
use App\Models\Post;
use App\Models\User;
use App\Services\Timeline\DTO\FeedResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class FeedService
{
    public const TYPE_FOR_YOU = 'for_you';
    public const TYPE_FOLLOWING = 'following';
    public const TYPE_LATEST = 'latest';
    public const TYPE_REELS = 'reels';

    public function __construct(
        private CandidateGenerationService $candidateGenerationService,
        private FeedRankingService $feedRankingService
    ) {
    }

    public function getFeed(User $user, array $filter = []): FeedResult
    {
        $page = data_get_integer($filter, 'page', 1);
        $type = $this->normalizeType(data_get($filter, 'type'));
        $perPage = (int) config('post.paginate_per');

        if($type === self::TYPE_LATEST || $this->shouldUseColdStartLatestFeed($user, $type, $filter)) {
            $timelinePosts = $this->candidateGenerationService
                ->latestQuery($user, $filter)
                ->simplePaginateManual($perPage, $page);

            $posts = $this->withProcessingPosts($user, $page, collect($timelinePosts->items()));

            return new FeedResult($posts, $this->meta($type, [
                'strategy' => $type === self::TYPE_LATEST ? 'chronological' : 'cold_start_chronological',
                'candidate_count' => count($timelinePosts->items()),
                'candidate_limit' => null,
                'scored' => false,
                'page' => $page,
                'per_page' => $perPage,
            ]));
        }

        $candidateLimit = $this->candidateGenerationService->candidateLimit(
            data_get_integer($filter, 'candidate_limit', CandidateGenerationService::DEFAULT_CANDIDATE_LIMIT)
        );
        $sessionId = $this->sessionId($filter);
        $seedHashId = $this->seedHashId($filter);
        $cacheKey = ($sessionId && empty(data_get($filter, 'onset')))
            ? $this->sessionCacheKey($user, $type, $sessionId, $candidateLimit, $seedHashId)
            : null;

        $cachedRanking = $cacheKey ? Cache::get($cacheKey) : null;

        if(is_array($cachedRanking)) {
            $rankedPosts = $this->postsFromCachedRanking($cachedRanking);
            $candidateCount = count($cachedRanking);
        }
        else {
            $candidates = $this->candidateGenerationService->getCandidates($user, $type, $filter);
            $rankedPosts = $this->feedRankingService->rank($user, $candidates, $type, $filter);
            $candidateCount = $candidates->count();

            if($type === self::TYPE_REELS) {
                $rankedPosts = $this->prependSeedReel($user, $rankedPosts, $seedHashId);
            }

            if($cacheKey) {
                Cache::put($cacheKey, $this->cacheableRanking($rankedPosts), now()->addMinutes(15));
            }
        }

        $pagePosts = $rankedPosts->slice(($page - 1) * $perPage, $perPage)->values();
        $posts = $this->withProcessingPosts($user, $page, $pagePosts);

        return new FeedResult($posts, $this->meta($type, [
            'strategy' => $type === self::TYPE_FOLLOWING ? 'relationship_freshness_ranking' : 'candidate_ranking_v1',
            'candidate_count' => $candidateCount,
            'candidate_limit' => $candidateLimit,
            'scored' => true,
            'page' => $page,
            'per_page' => $perPage,
            'session_id' => $sessionId,
        ]));
    }

    public function normalizeType(?string $type): string
    {
        $type = strtolower((string) $type);

        if(in_array($type, [self::TYPE_FOR_YOU, self::TYPE_FOLLOWING, self::TYPE_LATEST, self::TYPE_REELS], true)) {
            return $type;
        }

        return self::TYPE_FOR_YOU;
    }

    private function withProcessingPosts(User $user, int $page, Collection $posts): Collection
    {
        if($page > 1) {
            return $posts;
        }

        return $this->processingPosts($user)->merge($posts);
    }

    private function processingPosts(User $user)
    {
        return $user->posts()->where('status', PostStatus::PROCESSING_VIDEO)->get();
    }

    private function meta(string $type, array $meta): array
    {
        return [
            'feed' => array_merge([
                'type' => $type,
            ], $meta),
        ];
    }

    private function shouldUseColdStartLatestFeed(User $user, string $type, array $filter): bool
    {
        if($type !== self::TYPE_FOR_YOU || data_get_integer($filter, 'onset', 0) > 0) {
            return false;
        }

        $refreshReason = strtolower(substr(trim((string) data_get($filter, 'refresh_reason', '')), 0, 32));

        if(! in_array($refreshReason, ['initial', 'open', 'warm', 'resume'], true)) {
            return false;
        }

        return ! $this->hasPersonalizationSignals($user);
    }

    private function hasPersonalizationSignals(User $user): bool
    {
        if(DB::table(Table::FOLLOWS)
            ->where('follower_id', $user->id)
            ->where('status', FollowStatus::FOLLOWING->value)
            ->exists()) {
            return true;
        }

        if($user->interestScores()->where('score', '>', 0)->exists()) {
            return true;
        }

        return DB::table(Table::FEED_EVENTS)
            ->where('user_id', $user->id)
            ->exists();
    }

    private function sessionId(array $filter): ?string
    {
        $sessionId = trim((string) data_get($filter, 'session_id', ''));

        if($sessionId === '') {
            return null;
        }

        return substr($sessionId, 0, 80);
    }

    private function sessionCacheKey(User $user, string $type, string $sessionId, int $candidateLimit, string $seedHashId = ''): string
    {
        return 'timeline.feed.session.' . sha1(implode('|', [
            $user->id,
            $type,
            $sessionId,
            $candidateLimit,
            $seedHashId,
        ]));
    }

    private function seedHashId(array $filter): string
    {
        return substr(trim((string) data_get($filter, 'seed_hash_id', '')), 0, 80);
    }

    private function prependSeedReel(User $user, Collection $rankedPosts, string $seedHashId): Collection
    {
        if($seedHashId === '') {
            return $rankedPosts;
        }

        $seedPost = $this->candidateGenerationService->seedReelPost($user, $seedHashId);

        if(empty($seedPost)) {
            return $rankedPosts;
        }

        $existing = $rankedPosts->firstWhere('id', $seedPost->id);

        if($existing) {
            $seedPost = $existing;
        }
        else {
            $seedPost->setAttribute('ranking_score', 9999.0);
            $seedPost->setAttribute('ranking_signals', [
                'seed_reel' => 1.0,
            ]);
        }

        return collect([$seedPost])
            ->merge($rankedPosts->reject(fn(Post $post) => (int) $post->id === (int) $seedPost->id))
            ->values();
    }

    private function cacheableRanking(Collection $posts): array
    {
        return $posts->mapWithKeys(function(Post $post) {
            return [
                $post->id => [
                    'score' => (float) $post->ranking_score,
                    'signals' => $post->ranking_signals ?: [],
                ],
            ];
        })->all();
    }

    private function postsFromCachedRanking(array $ranking): Collection
    {
        $ids = array_map('intval', array_keys($ranking));

        if(empty($ids)) {
            return collect();
        }

        $posts = Post::timelineFormatPosts()
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        return collect($ids)->map(function(int $postId) use ($posts, $ranking) {
            $post = $posts->get($postId);

            if(empty($post)) {
                return null;
            }

            $post->setAttribute('ranking_score', (float) data_get($ranking, "{$postId}.score", 0));
            $post->setAttribute('ranking_signals', data_get($ranking, "{$postId}.signals", []));

            return $post;
        })->filter()->values();
    }
}
