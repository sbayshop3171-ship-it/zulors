<?php

namespace App\Models;

use App\Enums\Chat\GroupStatus;
use App\Enums\Chat\GroupPrivacy;
use App\Enums\Chat\GroupVisibility;
use Illuminate\Database\Eloquent\Model;
use App\Support\Casts\ModelTimestampCast;

class Group extends Model
{
    public $guarded = [];

    protected $casts = [
        'visibility' => GroupVisibility::class,
        'privacy' => GroupPrivacy::class,
        'status' => GroupStatus::class,
        'verified' => 'boolean',
        'created_at' => ModelTimestampCast::class,
        'updated_at' => ModelTimestampCast::class,
    ];
    
    public function scopeActive($query)
    {
        return $query->where('status', GroupStatus::ACTIVE);
    }

    public function chat()
    {
        return $this->belongsTo(Chat::class, 'chat_id', 'id');
    }

    public function hasDefaultAvatar()
    {
        return $this->avatar == config('chat.group.avatar');
    }

    public function getAvatarUrlAttribute()
    {
        if (empty($this->avatar) || $this->hasDefaultAvatar()) {
            return asset(config('chat.group.avatar'));
        }

        return storage_url($this->avatar, static_storage_disk());
    }

    public function isVerified()
    {
        return $this->verified;
    }

    public function reports()
    {
        return $this->morphMany(Report::class, 'reportable', 'reportable_type', 'reportable_id', 'id');
    }

    public function getParticipantsCountAttribute()
    {
        $chatData = $this->chat;

        if($chatData) {
            return $chatData->participants()->count();
        }

        return 0;
    }

    public function getUrlAttribute()
    {
        $chatData = $this->chat;

        if($chatData) {
            return url("messenger/group/$chatData->chat_id/show");
        }

        return '#';
    }
}
