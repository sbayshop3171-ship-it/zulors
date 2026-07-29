<?php

namespace App\Models;

use App\Database\Configs\Table;
use Illuminate\Database\Eloquent\Model;

class UserInterestScore extends Model
{
    public $table = Table::USER_INTEREST_SCORES;

    protected $guarded = [];

    protected $casts = [
        'score' => 'float',
        'last_event_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
