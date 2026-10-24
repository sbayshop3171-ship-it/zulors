<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserAdPreference extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'personalized_ads' => 'boolean',
            'consented_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
