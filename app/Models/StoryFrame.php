<?php

namespace App\Models;

use App\Enums\Story\StoryType;
use App\Enums\Story\StoryStatus;
use App\Enums\Story\StoryPrivacy;
use App\Support\Casts\ModelTimestampCast;
use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\Text\InteractsWithText;

class StoryFrame extends Model
{
    use InteractsWithText;
    
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'type' => StoryType::class,
        'privacy' => StoryPrivacy::class,
        'status' => StoryStatus::class,
        'created_at' => ModelTimestampCast::class,
        'expires_at' => ModelTimestampCast::class,
        'meta' => 'array',
        'is_highlight' => 'boolean'
    ];

    public function scopeActive($query)
    {
        return $query->where('status', StoryStatus::ACTIVE);
    }

    public function scopeRelevantStories($query)
    {
        return $query->active()->where('expires_at', '>', now());
    }
    
    public function story()
    {
        return $this->belongsTo(Story::class, 'story_id', 'id');
    }

    public function views()
    {
        return $this->hasMany(StoryView::class, 'story_frame_id', 'id');
    }

    public function media()
    {
        return $this->morphMany(Media::class, 'mediaable', 'mediaable_type', 'mediaable_id', 'id');
    }

    public function isExpired()
    {
        return $this->expires_at->isPast();
    }
}
