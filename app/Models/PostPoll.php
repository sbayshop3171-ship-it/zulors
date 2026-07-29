<?php

namespace App\Models;

use App\Database\Configs\Table;
use Illuminate\Database\Eloquent\Model;

class PostPoll extends Model
{

    public $table = Table::POST_POLLS;

    public $guarded = [];

    protected $casts = [
        'metadata' => 'array',
        'choices' => 'array',
        'is_anonymous' => 'boolean',
        'is_cancellable' => 'boolean',
        'votes' => 'array'
    ];

    public function post()
    {
        return $this->belongsTo(Post::class, 'post_id', 'id');
    }
}
