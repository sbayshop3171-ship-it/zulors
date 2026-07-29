<?php

namespace App\Models;

use App\Enums\User\ASRStatus;
use Illuminate\Database\Eloquent\Model;
use App\Support\Casts\ModelTimestampCast;

class AuthorshipRequest extends Model
{
    public $casts = [
        'status' => ASRStatus::class,
        'created_at' => ModelTimestampCast::class,
    ];

    public $guarded = [];

    public function scopePending($query)
    {
        return $query->where('status', ASRStatus::PENDING);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
