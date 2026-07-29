<?php

namespace App\Models;

use App\Enums\CensorLevel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Censor extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'word',
        'level',
    ];

    protected $casts = [
        'level' => CensorLevel::class,
    ];

    public function scopeBanned(Builder $query): Builder
    {
        return $query->where('level', CensorLevel::BANNED);
    }

    public function scopeReplaced(Builder $query): Builder
    {
        return $query->where('level', CensorLevel::REPLACED);
    }

    public function scopeWarning(Builder $query): Builder
    {
        return $query->where('level', CensorLevel::WARNING);
    }
}
