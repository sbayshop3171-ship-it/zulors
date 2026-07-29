<?php

namespace App\Models;

use App\Support\Casts\ModelTimestampCast;
use Illuminate\Database\Eloquent\Model;

class Locale extends Model
{
    protected $guarded = [];

    protected $casts = [
        'status' => 'boolean',
        'is_default' => 'boolean',
        'created_at' => ModelTimestampCast::class,
    ];

    public function isPermanent(): bool
    {
        return in_array($this->alpha_2_code, config('localization.permanent_locales'));
    }
}
