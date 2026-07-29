<?php

namespace App\Enums\Ad;

enum AdStatus: string
{
    case PUBLISHED = 'published';
    case PAUSED = 'paused';
    case COMPLETED = 'completed';
    case DRAFT = 'draft';

    public function isPublished(): bool
    {
        return $this === self::PUBLISHED;
    }

    public function isPaused(): bool
    {
        return $this === self::PAUSED;
    }

    public function label(): string
    {
        return match ($this) {
            self::PAUSED => __('labels.status_labels.paused'),
            self::COMPLETED => __('labels.status_labels.completed'),
            self::PUBLISHED => __('labels.status_labels.published'),
            self::DRAFT => __('labels.status_labels.draft'),
        };
    }

    public function emoji(): string
    {
        return match ($this) {
            self::PAUSED => '⏳',
            self::COMPLETED => '✅',
            self::PUBLISHED => '🚀',
            self::DRAFT => '🗒️',
        };
    }
}
