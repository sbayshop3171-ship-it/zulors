<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserAdInterestScore extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'score' => 'float',
            'events_count' => 'integer',
            'last_event_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
