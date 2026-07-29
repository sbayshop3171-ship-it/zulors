<?php

namespace App\Support\Casts;

use App\Support\VO\Balance;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class BalanceCast implements CastsAttributes
{
	public function get($model, string $key, $value, array $attributes)
	{
		if (empty($value)) {
            $value = 0;
        }
        
        return new Balance($value, $attributes['currency']);
	}

	public function set($model, string $key, $value, array $attributes)
	{
		if ($value instanceof Balance) {
            return $value->getAmount();
        }
        
        return $value;
	}
}
