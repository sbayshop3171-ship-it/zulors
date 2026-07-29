<?php

namespace App\Models;

use App\Enums\Wallet\CashoutStatus;
use App\Support\Casts\ModelTimestampCast;
use App\Support\Num;
use Illuminate\Database\Eloquent\Model;

class Cashout extends Model
{
    public $guarded = ['id'];

    public $casts = [
        'status' => CashoutStatus::class,
        'created_at' => ModelTimestampCast::class,
    ];

    public function scopePending($query)
    {
        return $query->where('status', CashoutStatus::PENDING);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function getFormattedAmountAttribute(): string
    {
        return Num::currency($this->amount, $this->currency);
    }

    public function getFormattedToPayAttribute(): string
    {
        return Num::currency($this->to_pay, $this->currency);
    }

    public function getFormattedCommissionFeeAttribute(): string
    {
        return Num::currency($this->amount - $this->to_pay, $this->currency);
    }
}
