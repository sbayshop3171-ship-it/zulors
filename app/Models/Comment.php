<?php

namespace App\Models;

use App\Models\Traits\Base\BaseModel;
use Illuminate\Database\Eloquent\Model;
use App\Support\Casts\ModelTimestampCast;
use App\Models\Traits\Text\InteractsWithText;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Comment extends Model
{
    use BaseModel,
        InteractsWithText;
    
    public $fillable = [
        'content',
        'user_id',
        'post_id',
        'parent_id',
        'thread_root_id',
        'text_language',
    ];

    protected $casts = [
        'created_at' => ModelTimestampCast::class,
        'edited_at' => 'datetime',
    ];

    public function post()
    {
        return $this->belongsTo(Post::class, 'post_id', 'id');
    }

    public function parent()
    {
        return $this->belongsTo(Comment::class, 'parent_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function replies()
    {
        return $this->hasMany(Comment::class, 'parent_id', 'id');
    }

    public function threadRoot()
    {
        return $this->belongsTo(Comment::class, 'thread_root_id');
    }

    public function threadReplies()
    {
        return $this->hasMany(Comment::class, 'thread_root_id');
    }

    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediaable', 'mediaable_type', 'mediaable_id', 'id');
    }

    public function reports(): MorphMany
    {
        return $this->morphMany(Report::class, 'reportable', 'reportable_type', 'reportable_id', 'id');
    }

    public function reactions()
    {
        return $this->morphMany(Reaction::class, 'reactable', 'reactable_type', 'reactable_id', 'id');
    }

    public function getPostHashIdAttribute(): string
	{
		return encode_id($this->post_id);
	}

    public function getIsEditedAttribute(): bool
    {
        return $this->edited_at !== null;
    }
}
