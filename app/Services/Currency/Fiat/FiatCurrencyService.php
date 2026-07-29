<?php

namespace App\Services\Currency\Fiat;

use App\Models\Currency;
use Illuminate\Support\Facades\Cache;

class FiatCurrencyService
{
	private $currencies;

	public function __construct()
	{
		$this->currencies = Cache::rememberForever('world_currencies', function() {
            return Currency::all();
        });
	}

	public function getPairedCurrencies()
	{
		return $this->currencies->map(function($currency) {
			return [
				'key' => $currency->alpha_3_code,
				'value' => $currency->name,
			];
		});
	}

	public function getCurrencyName(string $code)
	{
		$currency = $this->currencies->where('alpha_3_code', $code)->first();

		if(empty($currency)) {
			return null;
		}

		return $currency->name;
	}

	public function getCurrencyData(string $code)
	{
		$currency = $this->currencies->where('alpha_3_code', $code)->first();

		if(empty($currency)) {
			return null;
		}

		return $currency;
	}
}
