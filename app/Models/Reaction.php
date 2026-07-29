<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reaction extends Model
{
    protected $fillable = [
        'unified_id',
        'users',
        'native_symbol',
        'reactions_count'
    ];
    
    protected $casts = [
        'users' => 'array'
    ];

    public function reactable()
    {
        return $this->morphTo('reactable', 'reactable_type', 'reactable_id', 'id');
    }
}
