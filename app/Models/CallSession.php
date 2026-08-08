<?php

namespace App\Models;

use App\Database\Configs\Table;
use App\Enums\Call\CallMediaType;
use App\Enums\Call\CallStatus;
use Illuminate\Database\Eloquent\Model;

class CallSession extends Model
{
    public $table = Table::CALL_SESSIONS;

    public $guarded = [];

    public $casts = [
        'media_type' => CallMediaType::class,
        'status' => CallStatus::class,
        'metadata' => 'array',
        'started_at' => 'datetime',
        'answered_at' => 'datetime',
        'connected_at' => 'datetime',
        'ended_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function scopeActive($query)
    {
        return $query->whereIn('status', CallStatus::activeValues());
    }

    public function chat()
    {
        return $this->belongsTo(Chat::class, 'chat_id', 'id');
    }

    public function initiator()
    {
        return $this->belongsTo(User::class, 'initiator_id', 'id');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiver_id', 'id');
    }

    public function participants()
    {
        return $this->hasMany(CallParticipant::class, 'call_session_id', 'id');
    }

    public function isParticipant(int $userId): bool
    {
        return $this->initiator_id == $userId
            || $this->receiver_id == $userId
            || $this->participants()->where('user_id', $userId)->exists();
    }

    public function otherUserId(int $userId): ?int
    {
        if($this->initiator_id == $userId) {
            return $this->receiver_id;
        }

        if($this->receiver_id == $userId) {
            return $this->initiator_id;
        }

        return null;
    }
}
