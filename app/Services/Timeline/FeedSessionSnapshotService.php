<?php

namespace App\Services\Timeline;

use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class FeedSessionSnapshotService
{
    private const CACHE_TTL_MINUTES = 30;

    public function remember(User $user, string $type, ?string $sessionId, Collection $posts, int $windowSize = 50): array
    {
        $sessionId = trim((string) $sessionId);

        if($sessionId === '') {
            return [];
        }

        $snapshot = [
            'window_size' => $windowSize,
            'post_ids' => $posts->take($windowSize)->pluck('id')->map(fn($postId) => (int) $postId)->all(),
            'captured_at' => now()->toIso8601String(),
        ];

        Cache::put(
            $this->cacheKey($user->id, $type, $sessionId),
            $snapshot,
            now()->addMinutes(self::CACHE_TTL_MINUTES)
        );

        return $snapshot;
    }

    public function get(User $user, string $type, ?string $sessionId): array
    {
        $sessionId = trim((string) $sessionId);

        if($sessionId === '') {
            return [];
        }

        $snapshot = Cache::get($this->cacheKey($user->id, $type, $sessionId));

        return is_array($snapshot) ? $snapshot : [];
    }

    public function postsForSnapshot(User $user, string $type, ?string $sessionId): Collection
    {
        $snapshot = $this->get($user, $type, $sessionId);
        $postIds = collect(data_get($snapshot, 'post_ids', []))
            ->map(fn($postId) => (int) $postId)
            ->filter()
            ->values()
            ->all();

        if(empty($postIds)) {
            return collect();
        }

        $postIdOrder = array_flip($postIds);

        return Post::query()
            ->timelineFormatPosts()
            ->whereIn('id', $postIds)
            ->get()
            ->sortBy(fn(Post $post) => $postIdOrder[$post->id] ?? PHP_INT_MAX)
            ->values();
    }

    private function cacheKey(int $userId, string $type, string $sessionId): string
    {
        return "timeline.feed.session_snapshot.v1.{$userId}.{$type}.{$sessionId}";
    }
}
