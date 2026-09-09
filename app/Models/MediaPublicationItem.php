<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MediaPublicationItem extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];
    protected $casts = [
        'upload' => 'array', 'output' => 'array', 'metadata' => 'array', 'size' => 'integer',
        'generation' => 'integer', 'progress' => 'integer',
        'dispatched_at' => 'datetime', 'queued_at' => 'datetime', 'original_deleted_at' => 'datetime',
        'outputs_deleted_at' => 'datetime',
    ];

    public function publication() { return $this->belongsTo(MediaPublication::class, 'publication_id'); }
}
