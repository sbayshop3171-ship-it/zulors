<?php

namespace App\Models;

use App\Support\Casts\BalanceCast;
use Illuminate\Database\Eloquent\Model;

class Wallet extends Model
{
    public $guarded = [];

    public $timestamps = false;

    public $casts = [
        'balance' => BalanceCast::class,
    ];

    public function scopeExcludeSelf($query)
    {
        return $query->where('user_id', '!=', me()->id);
    }

    public function scopeWhereWalletNumber($query, $walletNumber)
    {
        return $query->where('wallet_number', $walletNumber);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function transactions()
    {
        return $this->hasMany(WalletTransaction::class, 'wallet_id', 'id');
    }
}
