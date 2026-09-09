<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MediaPublication extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];
    protected $casts = [
        'payload' => 'array', 'profile' => 'array', 'result' => 'array', 'has_video' => 'boolean',
        'expires_at' => 'datetime', 'published_at' => 'datetime',
    ];

    public function user() { return $this->belongsTo(User::class); }
    public function items() { return $this->hasMany(MediaPublicationItem::class, 'publication_id')->orderBy('position'); }
    public function terminal(): bool { return in_array($this->status, ['published', 'cancelled'], true); }
}
