<?php

namespace App\Models;

use App\Enums\ConfirmationType;
use Illuminate\Database\Eloquent\Model;

class Confirmation extends Model
{
    protected $fillable = [
        'user_id',
        'identifier',
        'type',
        'code',
        'expires_at',
        'confirmed'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'confirmed' => 'boolean',
        'type' => ConfirmationType::class
    ];
}
