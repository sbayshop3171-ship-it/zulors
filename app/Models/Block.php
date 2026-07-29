<?php

namespace App\Models;

use App\Support\Casts\ModelTimestampCast;
use Illuminate\Database\Eloquent\Model;

class Block extends Model
{
    public $guarded = ['id'];

    protected $casts = [
        'created_at' => ModelTimestampCast::class,
    ];

    public function blocker()
    {
        return $this->belongsTo(User::class, 'blocker_id', 'id');
    }

    public function blocked()
    {
        return $this->belongsTo(User::class, 'blocked_id', 'id');
    }
}
