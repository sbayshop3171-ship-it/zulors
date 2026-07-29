<?php

namespace App\Support\Casts;

use App\Support\DateFormatter;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class ModelTimestampCast implements CastsAttributes
{
	public function get($model, string $key, $value, array $attributes)
    {
        if (empty($value)) {
            return null;
        }
        
        return new DateFormatter($value);
    }

    public function set($model, string $key, $value, array $attributes)
    {
        if ($value instanceof DateFormatter) {
            return $value->getTimestamp();
        }
        
        return $value;
    }
}
