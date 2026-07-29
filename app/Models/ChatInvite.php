<?php

namespace App\Models;

use App\Enums\Chat\ChatInviteStatus;
use Illuminate\Database\Eloquent\Model;
use App\Support\Casts\ModelTimestampCast;

class ChatInvite extends Model
{
    public $guarded = [];

    public $casts = [
        'created_at' => ModelTimestampCast::class,
        'status' => ChatInviteStatus::class,
        'expires_at' => ModelTimestampCast::class,
    ];

    public function scopePending($query)
    {
        return $query->where('status', ChatInviteStatus::PENDING);
    }

    public function chat()
    {
        return $this->belongsTo(Chat::class, 'chat_id', 'id');
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id', 'id');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiver_id', 'id');
    }
}
