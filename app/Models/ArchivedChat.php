<?php

namespace App\Models;

use App\Enums\Chat\ChatType;
use Illuminate\Database\Eloquent\Model;

class ArchivedChat extends Model
{
    public $timestamps = false;

    public $guarded = [];

    public $casts = [
        'type' => ChatType::class
    ];
}
