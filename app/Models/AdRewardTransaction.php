<?php

namespace App\Models;

use App\Database\Configs\Table;
use Illuminate\Database\Eloquent\Model;

class AdRewardTransaction extends Model
{
    public $table = Table::AD_REWARD_TRANSACTIONS;

    protected $guarded = [];

    protected $casts = [
        'amount' => 'float',
        'metadata' => 'array',
    ];

    public function account()
    {
        return $this->belongsTo(AdRewardAccount::class, 'ad_reward_account_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function ad()
    {
        return $this->belongsTo(Ad::class, 'ad_id', 'id');
    }
}
