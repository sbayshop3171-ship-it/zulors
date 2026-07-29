<?php

namespace App\Models;

use App\Database\Configs\Table;
use App\Enums\Chat\MessageType;
use App\Models\Media;
use App\Support\Casts\ModelTimestampCast;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    public $guarded = [];

    public $casts = [
        'is_deleted' => 'boolean',
        'type' => MessageType::class,
        'created_at' => ModelTimestampCast::class
    ];

    public function scopeExcludeDeleted($query)
    {
        return $query->whereNotIn('id', function ($subQuery) {
            $subQuery->select('message_id')->from(Table::HIDDEN_MESSAGES)->where('user_id', me()->id);
        });
    }

    public function chat()
    {
        return $this->belongsTo(Chat::class, 'chat_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function participant()
    {
        return $this->belongsTo(ChatParticipant::class, 'participant_id', 'id');
    }

    public function parent()
    {
        return $this->belongsTo(Message::class, 'parent_id', 'id');
    }

    public function reactions()
    {
        return $this->morphMany(Reaction::class, 'reactable', 'reactable_type', 'reactable_id', 'id');
    }

    public function isSender()
    {
        return auth_check() ? $this->user_id == me()->id : false;
    }

    public function linkSnapshot()
    {
        return $this->morphOne(LinkSnapshot::class, 'linkable', 'linkable_type', 'linkable_id', 'id');
    }

    public function media()
    {
        return $this->morphOne(Media::class, 'mediaable', 'mediaable_type', 'mediaable_id', 'id');
    }

    public function isMessageTranslatable()
    {
        if($this->text_language) {
            return $this->text_language !== app()->getLocale();
        }

        return false;
    }
}
