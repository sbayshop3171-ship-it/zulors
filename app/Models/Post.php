<?php

namespace App\Models;

use App\Enums\Pin\PinType;
use App\Enums\Post\PostType;
use App\Enums\Post\PostStatus;
use App\Database\Configs\Table;
use App\Models\Traits\View\Viewable;
use App\Models\Traits\Base\BaseModel;
use Illuminate\Database\Eloquent\Model;
use App\Support\Casts\ModelTimestampCast;
use App\Models\Traits\Base\SupportsHashIds;
use App\Models\Traits\Bookmark\Bookmarkable;
use App\Models\Traits\Text\InteractsWithText;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Traits\Pagination\SupportsManualPagination;

class Post extends Model
{
    use HasFactory,
        Viewable,
        SupportsManualPagination,
        BaseModel,
        SupportsHashIds,
        Bookmarkable,
        InteractsWithText;

    public $table = Table::POSTS;

    public $guarded = [];

    protected $casts = [
        'type' => PostType::class,
        'status' => PostStatus::class,
        'is_sensitive' => 'boolean',
        'edited' => 'boolean',
        'profile_pinned' => 'boolean',
        'global_pinned' => 'boolean',
        'is_ai_generated' => 'boolean',
        'created_at' => ModelTimestampCast::class
    ];

    protected $attributes = [
        'content' => ''
    ];

    public function scopeActive($query)
    {
        return $query->where('status', PostStatus::ACTIVE);
    }

    public function scopeActiveById($query, $id)
    {
        return $query->active()->where('id', $id);
    }
    
    public function scopeExcludeSelf($query)
    {
        return $query->where('user_id', '!=', me()->id);
    }

    public function scopeTimelineFormatPosts($query, bool $includeProcessing = false)
    {
        if($includeProcessing) {
            $query->whereIn('status', [
                PostStatus::ACTIVE,
                PostStatus::PROCESSING_VIDEO,
            ]);
        }
        else {
            $query->active();
        }

        return $query->with(['user', 'media', 'poll', 'reactions', 'quotedPost.user', 'quotedPost.media', 'linkSnapshot', 'bookmarks' => function($query) {
            if(auth_check()) {
                $query->where('user_id', me()->id);
            }
            else {
                $query->whereRaw('1 = 0');
            }
        }, 'comments' => function($query) {
            $query->with('user:id,avatar')->limit(3);
        }]);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function linkSnapshot()
    {
        return $this->morphOne(LinkSnapshot::class, 'linkable', 'linkable_type', 'linkable_id', 'id');
    }

    public function topics()
    {
        return $this->hasMany(PostTopic::class, 'post_id', 'id');
    }

    public function videoMetric()
    {
        return $this->hasOne(PostVideoMetric::class, 'post_id', 'id');
    }

    public function quotingPost()
    {
        return $this->hasOne(Post::class, 'quote_post_id', 'id');
    }

    public function quotedPost()
    {
        return $this->belongsTo(Post::class, 'quote_post_id', 'id');
    }

    public function reports()
    {
        return $this->morphMany(Report::class, 'reportable', 'reportable_type', 'reportable_id', 'id');
    }

    public function pins()
    {
        return $this->morphMany(Pin::class, 'pinnable', 'pinnable_type', 'pinnable_id', 'id');
    }

    public function media()
    {
        return $this->morphMany(Media::class, 'mediaable', 'mediaable_type', 'mediaable_id', 'id');
    }

    public function poll()
    {
        return $this->hasOne(PostPoll::class, 'post_id', 'id');
    }

    public function reactions()
    {
        return $this->morphMany(Reaction::class, 'reactable', 'reactable_type', 'reactable_id', 'id');
    }

    public function comments()
    {
        return $this->hasMany(Comment::class, 'post_id', 'id');
    }

    public function getUrlAttribute()
    {
        return url("publication/{$this->hashid}");
    }

    public function isPinnedGlobal()
    {
        return $this->pins()->where('type', PinType::GLOBAL)->exists();
    }
}
