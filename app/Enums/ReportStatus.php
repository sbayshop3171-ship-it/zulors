<?php

namespace App\Enums;

enum ReportStatus: string
{
    case PENDING = 'pending';
    case IGNORED = 'ignored';
    case PROCESSED = 'processed';

    public function isPending(): bool
    {
        return $this === self::PENDING;
    }

    public function isIgnored(): bool
    {
        return $this === self::IGNORED;
    }

    public function isProcessed(): bool
    {
        return $this === self::PROCESSED;
    }

    public function label(): string
    {
        return match ($this) {
            self::PENDING => __('labels.status_labels.pending'),
            self::IGNORED => __('labels.status_labels.ignored'),
            self::PROCESSED => __('labels.status_labels.processed'),
        };
    }
    
    public function emoji(): string
    {
        return match ($this) {
            self::PENDING => '⏳',
            self::IGNORED => '❌',
            self::PROCESSED => '✅',
        };
    }
}
