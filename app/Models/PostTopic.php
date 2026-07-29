<?php

namespace App\Models;

use App\Database\Configs\Table;
use Illuminate\Database\Eloquent\Model;

class PostTopic extends Model
{
    public $table = Table::POST_TOPICS;

    protected $guarded = [];

    protected $casts = [
        'weight' => 'float',
    ];

    public function post()
    {
        return $this->belongsTo(Post::class, 'post_id', 'id');
    }
}
