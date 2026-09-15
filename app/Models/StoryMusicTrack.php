<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StoryMusicTrack extends Model
{
    protected $guarded = [];

    protected $casts = [
        'duration_seconds' => 'integer',
        'tags' => 'array',
        'meta' => 'array',
        'sort_order' => 'integer',
        'usage_count' => 'integer',
        'is_active' => 'boolean',
        'published_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }

    public function isAvailable(): bool
    {
        return $this->is_active && (empty($this->expires_at) || $this->expires_at->isFuture());
    }
}
