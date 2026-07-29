<?php

namespace App\Models;

use App\Enums\Pin\PinType;
use App\Enums\Pin\PinContent;
use Illuminate\Database\Eloquent\Model;
use App\Support\Casts\ModelTimestampCast;

class Pin extends Model
{
    protected $guarded = [];

    protected $casts = [
        'type' => PinType::class,
        'created_at' => ModelTimestampCast::class,
        'updated_at' => ModelTimestampCast::class,
    ];

    public function scopeGlobalPinnedPosts($query)
    {
        return $query->where('type', PinType::GLOBAL)
            ->where('content_type', PinContent::POST);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function pinnable()
    {
        return $this->morphTo('pinnable', 'pinnable_type', 'pinnable_id', 'id');
    }
}
