<?php

namespace App\Models;

use App\Database\Configs\Table;
use Illuminate\Database\Eloquent\Model;

class FeedEvent extends Model
{
    public $table = Table::FEED_EVENTS;

    protected $guarded = [];

    protected $casts = [
        'watch_time_seconds' => 'float',
        'duration_seconds' => 'float',
        'completion_rate' => 'float',
        'metadata' => 'array',
    ];

    public function post()
    {
        return $this->belongsTo(Post::class, 'post_id', 'id');
    }

    public function media()
    {
        return $this->belongsTo(Media::class, 'media_id', 'id');
    }
}
