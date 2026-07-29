<?php

namespace App\Models;

use App\Database\Configs\Table;
use Illuminate\Database\Eloquent\Model;

class AdImpression extends Model
{
    public $table = Table::AD_IMPRESSIONS;

    protected $guarded = [];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'last_clicked_at' => 'datetime',
    ];

    public function ad()
    {
        return $this->belongsTo(Ad::class, 'ad_id', 'id');
    }
}
