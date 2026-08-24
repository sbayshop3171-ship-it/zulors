<?php

namespace App\Services\Timeline;

use App\Models\Post;
use Illuminate\Support\Collection;

class PostAffinityService
{
    public function __construct(private TopicExtractionService $topicExtractionService)
    {
    }

    public function weightedKeysForPost(Post $post): array
    {
        $keys = [];

        if($post->user_id) {
            $keys[$this->authorKey((int) $post->user_id)] = 0.95;
        }

        if($languageKey = $this->languageKey($post)) {
            $keys[$languageKey] = 0.45;
        }

        if($mediaTypeKey = $this->mediaTypeKey($post)) {
            $keys[$mediaTypeKey] = 0.40;
        }

        if($durationBucketKey = $this->durationBucketKey($post)) {
            $keys[$durationBucketKey] = 0.55;
        }

        if($soundSignatureKey = $this->soundSignatureKey($post)) {
            $keys[$soundSignatureKey] = 0.70;
        }

        foreach($this->topicKeys($post) as $topic) {
            $keys[$topic] = 1.00;
        }

        return $keys;
    }

    public function affinityKeysForPosts(Collection $posts, array $prefixes = []): array
    {
        return $posts
            ->flatMap(function(Post $post) {
                return array_keys($this->weightedKeysForPost($post));
            })
            ->filter(function(string $key) use ($prefixes) {
                if(empty($prefixes)) {
                    return true;
                }

                foreach($prefixes as $prefix) {
                    if(str_starts_with($key, $prefix)) {
                        return true;
                    }
                }

                return false;
            })
            ->unique()
            ->values()
            ->all();
    }

    public function topicKeys(Post $post): array
    {
        return $this->topicExtractionService
            ->ensurePostTopics($post)
            ->pluck('topic')
            ->map(fn($topic) => $this->topicExtractionService->normalizeTopic($topic))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function authorKey(int $authorId): string
    {
        return "author:{$authorId}";
    }

    public function languageKey(Post $post): ?string
    {
        $language = $this->normalizeToken((string) ($post->text_language ?? ''));

        return $language ? "language:{$language}" : null;
    }

    public function mediaTypeKey(Post $post): ?string
    {
        $mediaType = $this->normalizeToken((string) $post->type->value);

        return $mediaType ? "media_type:{$mediaType}" : null;
    }

    public function durationBucketKey(Post $post): ?string
    {
        $durationSeconds = $this->durationSeconds($post);

        if($durationSeconds <= 0) {
            return null;
        }

        return match(true) {
            $durationSeconds <= 20 => 'duration_bucket:short',
            $durationSeconds <= 90 => 'duration_bucket:medium',
            default => 'duration_bucket:long',
        };
    }

    public function soundSignatureKey(Post $post): ?string
    {
        $media = $post->media->first();
        $metadata = is_array($media?->metadata) ? $media->metadata : [];
        $soundSignature = $this->normalizeToken((string) (
            data_get($metadata, 'sound_signature')
            ?: data_get($metadata, 'audio_signature')
            ?: ''
        ));

        return $soundSignature ? "sound_signature:{$soundSignature}" : null;
    }

    private function durationSeconds(Post $post): float
    {
        $media = $post->media->first();
        $metadata = is_array($media?->metadata) ? $media->metadata : [];
        $duration = data_get($metadata, 'duration.seconds', data_get($metadata, 'duration_seconds', 0));

        return is_numeric($duration) ? max(0.0, (float) $duration) : 0.0;
    }

    private function normalizeToken(?string $value): ?string
    {
        $value = trim((string) $value);

        if($value === '') {
            return null;
        }

        if(function_exists('mb_strtolower')) {
            return mb_strtolower($value, 'UTF-8');
        }

        return strtolower($value);
    }
}
