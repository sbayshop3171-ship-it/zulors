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
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
