<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdDelivery extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'reason_codes' => 'array',
            'served_at' => 'datetime',
            'viewable_at' => 'datetime',
            'cta_clicked_at' => 'datetime',
            'converted_at' => 'datetime',
            'negative_feedback_at' => 'datetime',
            'watch_time_seconds' => 'decimal:2',
            'completion_rate' => 'decimal:4',
        ];
    }

    public function ad()
    {
        return $this->belongsTo(Ad::class, 'ad_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
