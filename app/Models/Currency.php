<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Support\Casts\ModelTimestampCast;

class Currency extends Model
{
    protected $casts = [
        'status' => 'boolean',
        'created_at' => ModelTimestampCast::class,
    ];

    protected $guarded = [];
}
