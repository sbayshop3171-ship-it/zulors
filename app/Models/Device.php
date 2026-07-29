<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
    public $timestamps = false;

    public $guarded = [];

    public $casts = [
        'last_online' => 'datetime',
        'is_terminated' => 'boolean',
        'is_location_unknown' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
