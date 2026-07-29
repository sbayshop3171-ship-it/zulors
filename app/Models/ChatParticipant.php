<?php

namespace App\Models;

use App\Support\Casts\ModelTimestampCast;
use Illuminate\Database\Eloquent\Model;

class ChatParticipant extends Model
{
    public $casts = [
        'metadata' => 'array',
        'joined_at' => ModelTimestampCast::class
    ];

    public $guarded = [];

    public $timestamps = false;

    public function chat()
    {
        return $this->belongsTo(Chat::class, 'chat_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function messages()
    {
        return $this->hasMany(Message::class, 'participant_id', 'id');
    }
}
