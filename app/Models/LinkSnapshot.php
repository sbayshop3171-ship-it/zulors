<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LinkSnapshot extends Model
{
    protected $guarded = [];

    public $timestamps = false;

    protected $casts = [
        'metadata' => 'array'
    ];

    public function linkable()
    {
        return $this->morphTo();
    }
}
