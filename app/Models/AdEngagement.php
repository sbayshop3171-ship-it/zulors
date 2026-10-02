<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdEngagement extends Model
{
    protected $guarded = [];

    public function ad() { return $this->belongsTo(Ad::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function parent() { return $this->belongsTo(self::class, 'parent_id'); }
    public function replies() { return $this->hasMany(self::class, 'parent_id'); }
}
