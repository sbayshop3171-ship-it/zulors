<?php

namespace App\Models;

use App\Database\Configs\Table;
use Illuminate\Database\Eloquent\Model;

class AdRewardAccount extends Model
{
    public $table = Table::AD_REWARD_ACCOUNTS;

    protected $guarded = [];

    protected $casts = [
        'monthly_amount' => 'float',
        'available_amount' => 'float',
        'used_amount' => 'float',
        'expires_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function transactions()
    {
        return $this->hasMany(AdRewardTransaction::class, 'ad_reward_account_id', 'id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
