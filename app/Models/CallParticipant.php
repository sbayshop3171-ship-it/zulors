<?php

namespace App\Models;

use App\Database\Configs\Table;
use App\Enums\Call\CallStatus;
use Illuminate\Database\Eloquent\Model;

class CallParticipant extends Model
{
    public $table = Table::CALL_PARTICIPANTS;

    public $guarded = [];

    public $casts = [
        'status' => CallStatus::class,
        'metadata' => 'array',
        'joined_at' => 'datetime',
        'left_at' => 'datetime',
    ];

    public function callSession()
    {
        return $this->belongsTo(CallSession::class, 'call_session_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
