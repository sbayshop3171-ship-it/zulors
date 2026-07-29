<?php

namespace App\Models;

use App\Support\Casts\ModelTimestampCast;
use Illuminate\Database\Eloquent\Model;

class BusinessAccount extends Model
{
    public $timestamps = false;

    public $guarded = [];

    public $casts = [
        'billing_address' => 'array',
        'updated_at' => ModelTimestampCast::class,
        'verified' => 'boolean',
        'is_reviewed' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
