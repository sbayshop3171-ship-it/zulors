<?php

namespace App\Models;

use App\Support\Num;
use App\Enums\Payment\PaymentType;
use App\Enums\Payment\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use App\Support\Casts\ModelTimestampCast;

class Payment extends Model
{
    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
        'status' => PaymentStatus::class,
        'payment_type' => PaymentType::class,
        'created_at' => ModelTimestampCast::class,
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function getProviderNameAttribute(): string
    {
        $providers = config('payment.providers');

        if(! empty($providers[$this->payment_method])) {
            return $providers[$this->payment_method]['name'];
        }

        return 'Unknown';
    }

    public function getFormattedAmountAttribute(): string
    {
        return Num::currency($this->amount, $this->currency);
    }
}
