<?php

namespace App\Models;

use App\Support\Num;
use App\Enums\Wallet\TransactionType;
use App\Enums\Wallet\TransactionStatus;
use Illuminate\Database\Eloquent\Model;
use App\Support\Casts\ModelTimestampCast;
use App\Enums\Wallet\TransactionDirection;

class WalletTransaction extends Model
{
    public $guarded = [];

    public $casts = [
        'metadata' => 'array',
        'status' => TransactionStatus::class,
        'transaction_type' => TransactionType::class,
        'direction' => TransactionDirection::class,
        'created_at' => ModelTimestampCast::class
    ];

    public function wallet()
    {
        return $this->belongsTo(Wallet::class, 'wallet_id', 'id');
    }

    public function getCommissionAmountAttribute(): float
    {
        return $this->amount * $this->commission / 100;
    }

    public function getFormattedCommissionAmountAttribute(): string
    {
        return Num::currency($this->commission_amount, $this->currency);
    }

    public function getTnxIdAttribute(): string
    {
        return 'TXN-' . Num::leadingZero($this->id);
    }

    public function getFormattedAmountAttribute(): string
    {
        return Num::currency($this->amount, $this->currency);
    }

    public function getCurrencyNameAttribute(): string
    {
        return Num::currencyName($this->currency);
    }
}
