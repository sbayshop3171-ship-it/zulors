<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Mute extends Model
{
    public $guarded = ['id'];

    public function muter()
    {
        return $this->belongsTo(User::class, 'muter_id', 'id');
    }

    public function muted()
    {
        return $this->belongsTo(User::class, 'muted_id', 'id');
    }
}
