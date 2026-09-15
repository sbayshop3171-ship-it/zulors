<?php

namespace App\Support\StoryMusic;

class StoryMusicSearchIndex
{
    public static function build(array $parts): string
    {
        $text = collect($parts)
            ->flatten()
            ->filter(fn ($value) => filled($value))
            ->map(fn ($value) => self::normalize((string) $value))
            ->filter()
            ->unique()
            ->implode(' ');

        return str($text)
            ->squish()
            ->limit(5000, '')
            ->toString();
    }

    public static function normalize(string $value): string
    {
        return str($value)
            ->lower()
            ->replace(['_', '-', '#', '@'], ' ')
            ->replaceMatches('/[^\pL\pN\s]+/u', ' ')
            ->squish()
            ->toString();
    }

    public static function keywords(mixed $value): array
    {
        if(is_array($value)) {
            return collect($value)
                ->flatten()
                ->map(fn ($item) => (string) $item)
                ->flatMap(fn (string $item) => preg_split('/[,|]+/', $item) ?: [])
                ->map(fn (string $item) => trim($item))
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        return collect(preg_split('/[,|]+/', (string) $value) ?: [])
            ->map(fn (string $item) => trim($item))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
