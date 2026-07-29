<?php

namespace App\Models;

use App\Enums\BlacklistType;
use App\Support\Casts\ModelTimestampCast;
use Illuminate\Database\Eloquent\Model;

class Blacklist extends Model
{
    public $timestamps = false;
    
    public $casts = [
        'type' => BlacklistType::class,
        'added_at' => ModelTimestampCast::class,
        'expires_at' => ModelTimestampCast::class,
    ];

    public $guarded = [];

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id', 'id');
    }
}
