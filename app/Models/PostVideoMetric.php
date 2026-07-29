<?php

namespace App\Models;

use App\Database\Configs\Table;
use Illuminate\Database\Eloquent\Model;

class PostVideoMetric extends Model
{
    public $table = Table::POST_VIDEO_METRICS;

    protected $guarded = [];

    protected $casts = [
        'watch_time_seconds' => 'float',
        'avg_completion_rate' => 'float',
        'completion_rate' => 'float',
        'skip_rate' => 'float',
        'rewatch_rate' => 'float',
        'intelligence_score' => 'float',
        'last_event_at' => 'datetime',
    ];

    public function post()
    {
        return $this->belongsTo(Post::class, 'post_id', 'id');
    }
}
