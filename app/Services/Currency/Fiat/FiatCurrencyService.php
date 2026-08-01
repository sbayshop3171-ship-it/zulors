<?php

namespace App\Services\Currency\Fiat;

use App\Models\Currency;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class FiatCurrencyService
{
    private const CACHE_KEY = 'world_currencies';

	private $currencies;

	public function __construct()
	{
		$this->currencies = Cache::rememberForever(self::CACHE_KEY, function() {
            return Currency::query()
                ->orderBy('order')
                ->orderBy('name')
                ->get();
        });
	}

	public function getPairedCurrencies()
	{
		return $this->currencies->where('status', true)->map(function($currency) {
			return [
				'key' => $currency->alpha_3_code,
				'value' => "{$currency->alpha_3_code} - {$currency->name} ({$currency->symbol})",
			];
		})->values();
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

    public function getDefaultCurrencyCodeForUser(?User $user = null): string
    {
        $country = data_get($user, 'businessAccount.country') ?: data_get($user, 'country', '');
        $mappedCurrency = $this->currencyCodeForCountry(strtoupper((string) $country));

        if ($mappedCurrency && $this->isActiveCurrency($mappedCurrency)) {
            return $mappedCurrency;
        }

        $configuredCurrency = config('app.default_currency') ?: 'USD';

        if ($this->isActiveCurrency($configuredCurrency)) {
            return $configuredCurrency;
        }

        if ($this->isActiveCurrency('USD')) {
            return 'USD';
        }

        return $this->currencies->firstWhere('status', true)?->alpha_3_code ?: $configuredCurrency;
    }

    public static function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private function isActiveCurrency(string $code): bool
    {
        $currency = $this->currencies->firstWhere('alpha_3_code', strtoupper($code));

        return ! empty($currency) && $currency->status;
    }

    private function currencyCodeForCountry(string $country): ?string
    {
        if (empty($country)) {
            return null;
        }

        return match ($country) {
            'BD' => 'BDT',
            'IN' => 'INR',
            'US' => 'USD',
            'GB' => 'GBP',
            'CA' => 'CAD',
            'AU' => 'AUD',
            'AE' => 'AED',
            'SA' => 'SAR',
            'MY' => 'MYR',
            'SG' => 'SGD',
            'CN' => 'CNY',
            'JP' => 'JPY',
            'KR' => 'KRW',
            'BR' => 'BRL',
            'TR' => 'TRY',
            'AT', 'BE', 'CY', 'DE', 'EE', 'ES', 'FI', 'FR', 'GR', 'HR', 'IE',
            'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'PT', 'SI', 'SK' => 'EUR',
            default => null,
        };
    }
}
