<?php

namespace App\Support\StoryMusic;

use App\Models\Media;
use App\Models\Post;
use App\Models\StoryFrame;

class OriginalAudioEligibility
{
    public static function normalizeCategory(?string $category): ?string
    {
        $category = str($category ?? '')
            ->trim()
            ->lower()
            ->replace(['-', ' '], '_')
            ->toString();

        return filled($category) ? $category : null;
    }

    public static function allowedCategories(): array
    {
        return collect(config('story_music.original_audio.allowed_categories', []))
            ->map(fn ($category) => self::normalizeCategory((string) $category))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public static function categoryFor(Media $media): ?string
    {
        $metadata = $media->metadata ?? [];
        $mediaable = $media->relationLoaded('mediaable') ? $media->mediaable : null;

        foreach(self::categoryMetadataKeys() as $key) {
            $category = self::normalizeCategory((string) data_get($metadata, $key));

            if($category) {
                return $category;
            }
        }

        if($mediaable instanceof StoryFrame) {
            foreach(self::categoryMetadataKeys() as $key) {
                $category = self::normalizeCategory((string) data_get($mediaable->meta ?? [], $key));

                if($category) {
                    return $category;
                }
            }
        }

        if($mediaable instanceof Post) {
            foreach(self::categoryMetadataKeys() as $key) {
                $category = self::normalizeCategory((string) data_get($mediaable, $key));

                if($category) {
                    return $category;
                }
            }
        }

        return null;
    }

    public static function isAllowedCategory(?string $category): bool
    {
        $category = self::normalizeCategory($category);

        return $category !== null && in_array($category, self::allowedCategories(), true);
    }

    public static function shouldAutoExtract(Media $media, bool $includeUncategorized = false): bool
    {
        if($includeUncategorized) {
            return true;
        }

        if(! (bool) config('story_music.original_audio.require_allowed_category', true)) {
            return true;
        }

        return self::isAllowedCategory(self::categoryFor($media));
    }

    public static function applyCategory(Media $media, string $category): Media
    {
        $category = self::normalizeCategory($category) ?: 'reel';
        $metadata = $media->metadata ?? [];
        $metadata['story_music'] = array_merge((array) data_get($metadata, 'story_music', []), [
            'category' => $category,
            'auto_extract' => true,
        ]);

        $media->metadata = $metadata;

        return $media;
    }

    public static function storyMusicMetadataFromRequest($request): array
    {
        $category = self::normalizeCategory(
            (string) ($request->input('story_music_category')
                ?: $request->input('original_audio_category')
                ?: $request->input('upload_category')
                ?: $request->input('content_category'))
        );

        if(! self::isAllowedCategory($category)) {
            return [];
        }

        return [
            'story_music' => [
                'category' => $category,
                'auto_extract' => true,
            ],
        ];
    }

    public static function categoryValidationRule(): string
    {
        return 'nullable|string|max:40|in:' . implode(',', self::allowedCategories());
    }

    private static function categoryMetadataKeys(): array
    {
        return [
            'story_music.category',
            'story_music.original_audio_category',
            'story_music.source_category',
            'original_audio_category',
            'story_music_category',
            'music_category',
            'upload_category',
            'content_category',
            'source_category',
        ];
    }
}
