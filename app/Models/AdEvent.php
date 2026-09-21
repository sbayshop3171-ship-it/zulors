<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdEvent extends Model
{
    protected $guarded = [];

    protected $casts = [
        'watch_time_seconds' => 'float',
        'completion_rate' => 'float',
    ];
}