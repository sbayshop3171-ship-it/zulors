<?php

namespace App\Models;

use App\Support\Casts\ModelTimestampCast;
use Illuminate\Database\Eloquent\Model;

class StoryView extends Model
{
    public $timestamps = false;

    protected $casts = [
        'viewed_at' => ModelTimestampCast::class
    ];

    protected $guarded = [];

    public function scopeWithUser($query)
    {
        return $query->with(['user:id,avatar,first_name,last_name,username,verified']);
    }

    public function story()
    {
        return $this->belongsTo(Story::class, 'story_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
