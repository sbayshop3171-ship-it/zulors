<?php

namespace App\Models;

use App\Database\Configs\Table;
use Illuminate\Database\Eloquent\Model;

class UserSafetyScore extends Model
{
    public $table = Table::USER_SAFETY_SCORES;

    protected $guarded = [];

    protected $casts = [
        'trust_score' => 'float',
        'spam_score' => 'float',
        'frozen_until' => 'datetime',
        'last_violation_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
