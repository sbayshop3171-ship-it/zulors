<?php

namespace App\Services\Timeline;

use App\Enums\Post\PostStatus;
use App\Models\User;
use App\Services\Timeline\DTO\FeedResult;
use Illuminate\Support\Collection;

class FeedService
{
    public const TYPE_FOR_YOU = 'for_you';
    public const TYPE_FOLLOWING = 'following';
    public const TYPE_LATEST = 'latest';

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

        if($type === self::TYPE_LATEST) {
            $timelinePosts = $this->candidateGenerationService
                ->latestQuery($user, $filter)
                ->simplePaginateManual($perPage, $page);

            $posts = $this->withProcessingPosts($user, $page, collect($timelinePosts->items()));

            return new FeedResult($posts, $this->meta($type, [
                'strategy' => 'chronological',
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
        $candidates = $this->candidateGenerationService->getCandidates($user, $type, $filter);
        $rankedPosts = $this->feedRankingService->rank($user, $candidates, $type);
        $pagePosts = $rankedPosts->slice(($page - 1) * $perPage, $perPage)->values();
        $posts = $this->withProcessingPosts($user, $page, $pagePosts);

        return new FeedResult($posts, $this->meta($type, [
            'strategy' => $type === self::TYPE_FOLLOWING ? 'relationship_freshness_ranking' : 'candidate_ranking_v1',
            'candidate_count' => $candidates->count(),
            'candidate_limit' => $candidateLimit,
            'scored' => true,
            'page' => $page,
            'per_page' => $perPage,
        ]));
    }

    public function normalizeType(?string $type): string
    {
        $type = strtolower((string) $type);

        if(in_array($type, [self::TYPE_FOR_YOU, self::TYPE_FOLLOWING, self::TYPE_LATEST], true)) {
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
}
