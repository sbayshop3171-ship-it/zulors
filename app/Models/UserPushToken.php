<?php

namespace App\Models;

use App\Database\Configs\Table;
use Illuminate\Database\Eloquent\Model;

class UserPushToken extends Model
{
    public $table = Table::USER_PUSH_TOKENS;

    protected $guarded = [];

    protected $casts = [
        'token' => 'encrypted',
        'last_used_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function scopeActive($query)
    {
        return $query->whereNull('revoked_at');
    }
}
