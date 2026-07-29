<?php

namespace App\Models;

use App\Enums\Story\StoryStatus;
use Illuminate\Database\Eloquent\Model;

class Story extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    public function scopeActive($query)
    {
        return $query->whereHas('frames', function ($hasQuery) {
            $hasQuery->where('expires_at', '>', now())->where('status', StoryStatus::ACTIVE);
        });
    }

    public function scopeWithRelations($query)
    {
        return $query->with([
            'user:id,avatar,first_name,last_name,username,verified',
            'frames' => function($withQuery) {
                $withQuery->relevantStories();
            }
        ]);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function frames()
    {
        return $this->hasMany(StoryFrame::class, 'story_id', 'id');
    }

    public function activeFramesCount()
    {
        return $this->frames()->relevantStories()->count();
    }

    public function getUrlAttribute()
    {
        return url("stories/{$this->story_uuid}");
    }
}
