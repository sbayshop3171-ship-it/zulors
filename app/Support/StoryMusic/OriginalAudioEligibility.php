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

        $storyMusic = array_filter([
            'category' => $category,
            'auto_extract' => true,
            'title' => self::firstInput($request, ['story_music_title', 'music_title', 'song_title', 'title']),
            'artist' => self::firstInput($request, ['story_music_artist', 'music_artist', 'song_artist', 'artist']),
            'album' => self::firstInput($request, ['story_music_album', 'music_album', 'song_album', 'album']),
            'mood' => self::firstInput($request, ['story_music_mood', 'music_mood', 'mood']),
            'genre' => self::firstInput($request, ['story_music_genre', 'music_genre', 'genre']),
            'lyrics_keywords' => self::firstInput($request, ['lyrics_keywords', 'lyric_keywords', 'music_lyrics_keywords']),
            'search_keywords' => self::firstInput($request, ['search_keywords', 'music_search_keywords', 'song_keywords']),
            'recognition_provider' => self::firstInput($request, ['recognition_provider', 'music_recognition_provider']),
            'recognized_title' => self::firstInput($request, ['recognized_title', 'matched_title']),
            'recognized_artist' => self::firstInput($request, ['recognized_artist', 'matched_artist']),
            'recognition_confidence' => self::firstInput($request, ['recognition_confidence', 'match_confidence']),
        ], fn ($value) => filled($value) || $value === true);

        return [
            'story_music' => $storyMusic,
        ];
    }

    public static function categoryValidationRule(): string
    {
        return 'nullable|string|max:40|in:' . implode(',', self::allowedCategories());
    }

    public static function metadataValidationRules(): array
    {
        return [
            'story_music_category' => [self::categoryValidationRule()],
            'original_audio_category' => [self::categoryValidationRule()],
            'upload_category' => [self::categoryValidationRule()],
            'content_category' => [self::categoryValidationRule()],
            'story_music_title' => ['nullable', 'string', 'max:120'],
            'music_title' => ['nullable', 'string', 'max:120'],
            'song_title' => ['nullable', 'string', 'max:120'],
            'story_music_artist' => ['nullable', 'string', 'max:120'],
            'music_artist' => ['nullable', 'string', 'max:120'],
            'song_artist' => ['nullable', 'string', 'max:120'],
            'story_music_album' => ['nullable', 'string', 'max:120'],
            'music_album' => ['nullable', 'string', 'max:120'],
            'song_album' => ['nullable', 'string', 'max:120'],
            'story_music_mood' => ['nullable', 'string', 'max:60'],
            'music_mood' => ['nullable', 'string', 'max:60'],
            'mood' => ['nullable', 'string', 'max:60'],
            'story_music_genre' => ['nullable', 'string', 'max:60'],
            'music_genre' => ['nullable', 'string', 'max:60'],
            'genre' => ['nullable', 'string', 'max:60'],
            'lyrics_keywords' => ['nullable', 'max:1000'],
            'lyric_keywords' => ['nullable', 'max:1000'],
            'music_lyrics_keywords' => ['nullable', 'max:1000'],
            'search_keywords' => ['nullable', 'max:1000'],
            'music_search_keywords' => ['nullable', 'max:1000'],
            'song_keywords' => ['nullable', 'max:1000'],
            'recognition_provider' => ['nullable', 'string', 'max:60'],
            'music_recognition_provider' => ['nullable', 'string', 'max:60'],
            'recognized_title' => ['nullable', 'string', 'max:120'],
            'matched_title' => ['nullable', 'string', 'max:120'],
            'recognized_artist' => ['nullable', 'string', 'max:120'],
            'matched_artist' => ['nullable', 'string', 'max:120'],
            'recognition_confidence' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'match_confidence' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
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

    private static function firstInput($request, array $keys): mixed
    {
        foreach($keys as $key) {
            $value = $request->input($key);

            if(filled($value)) {
                return $value;
            }
        }

        return null;
    }
}
