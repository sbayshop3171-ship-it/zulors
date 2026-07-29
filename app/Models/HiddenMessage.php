<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HiddenMessage extends Model
{
    public $timestamps = false;

    public $guarded = [];
}
