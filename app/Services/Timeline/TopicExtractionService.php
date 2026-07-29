<?php

namespace App\Services\Timeline;

use App\Models\Post;
use Illuminate\Support\Collection;

class TopicExtractionService
{
    public const MAX_TOPICS_PER_POST = 10;

    public function extractFromText(?string $text): array
    {
        if(empty($text)) {
            return [];
        }

        preg_match_all('/#([\p{L}\p{M}\p{N}_][\p{L}\p{M}\p{N}_-]{0,63})/u', $text, $matches);

        return collect($matches[1] ?? [])
            ->map(fn($topic) => $this->normalizeTopic($topic))
            ->filter()
            ->unique()
            ->take(self::MAX_TOPICS_PER_POST)
            ->values()
            ->all();
    }

    public function normalizeTopic(?string $topic): ?string
    {
        $topic = trim((string) $topic);
        $topic = ltrim($topic, '#');
        $topic = trim($topic, "_- \t\n\r\0\x0B");

        if($topic === '') {
            return null;
        }

        if(function_exists('mb_strtolower')) {
            return mb_strtolower($topic, 'UTF-8');
        }

        return strtolower($topic);
    }

    public function syncPostTopics(Post $post): Collection
    {
        $topics = collect($this->extractFromText($post->content));

        if($topics->isEmpty()) {
            $post->topics()->delete();

            return collect();
        }

        $post->topics()->whereNotIn('topic', $topics->all())->delete();

        $topics->each(function(string $topic) use ($post) {
            $post->topics()->updateOrCreate([
                'topic' => $topic,
            ], [
                'source' => 'hashtag',
                'weight' => 1,
            ]);
        });

        return $post->topics()->whereIn('topic', $topics->all())->get();
    }

    public function ensurePostTopics(Post $post): Collection
    {
        if($post->relationLoaded('topics')) {
            if($post->topics->isNotEmpty()) {
                return $post->topics;
            }

            if(empty($this->extractFromText($post->content))) {
                return collect();
            }
        }

        if($post->topics()->exists()) {
            return $post->topics()->get();
        }

        return $this->syncPostTopics($post);
    }
}
