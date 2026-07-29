<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SocialLink extends Model
{
    protected $fillable = [
        'linkable_type',
        'linkable_id',
        'platform',
        'url',
        'svg_icon',
        'status'
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    public function linkable()
    {
        return $this->morphTo();
    }

    public function getIconUrlAttribute()
    {
        $theme = theme_name();
        
        return asset("assets/icons/social-media/{$theme}/{$this->platform}.png");
    }
}
