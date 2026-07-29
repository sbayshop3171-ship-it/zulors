<?php

namespace App\Models;

use App\Enums\Store\StoreStatus;
use Illuminate\Database\Eloquent\Model;

class Store extends Model
{

    public $guarded = [];

    public $casts = [
        'status' => StoreStatus::class
    ];

    public function products()
    {
        return $this->hasMany(Product::class, 'store_id', 'id');
    }
}
