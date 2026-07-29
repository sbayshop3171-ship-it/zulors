<?php

namespace App\Support;

use Illuminate\Support\Number;
use App\Services\Currency\Fiat\FiatCurrencyService;

class Num
{
	public static function abbreviate($number = 0)
	{
		if(! extension_loaded('intl')) {
			return self::abbreviateWithoutIntl($number);
		}

		return Number::abbreviate($number, 0, 1);
	}

	public static function currency($number = 0, $in = null)
	{
		$in = $in ?? config('app.default_currency');

		if(! extension_loaded('intl')) {
			return self::currencyWithoutIntl($number, $in);
		}

		$formatted = Number::currency($number, $in, app()->getLocale());

		return preg_replace('/([.,])0{2}(?=(?:\s*[\p{Sc}\p{L}]+)?$)/u', '', $formatted);
	}

	public static function currencyName($in = null)
	{
		$fiatCurrencyService = app(FiatCurrencyService::class);
		$currencyName = $fiatCurrencyService->getCurrencyName($in);

		if(empty($currencyName)) {
			return $in;
		}
		
		return $currencyName;
	}

	public static function leadingZero($number = 0, $length = 11)
	{
		return str_pad($number, $length, '0', STR_PAD_LEFT);
	}

	private static function abbreviateWithoutIntl($number)
	{
		$number = (float) $number;
		$absoluteNumber = abs($number);
		$units = [
			1000000000000 => 'T',
			1000000000 => 'B',
			1000000 => 'M',
			1000 => 'K',
		];

		foreach($units as $value => $suffix) {
			if($absoluteNumber >= $value) {
				$decimals = $absoluteNumber >= ($value * 10) ? 0 : 1;

				return self::trimTrailingZeros(number_format($number / $value, $decimals, '.', '')) . $suffix;
			}
		}

		return self::trimTrailingZeros(number_format($number, 0, '.', ''));
	}

	private static function currencyWithoutIntl($number, string $in)
	{
		$fiatCurrencyService = app(FiatCurrencyService::class);
		$currency = $fiatCurrencyService->getCurrencyData($in);
		$symbol = $currency?->symbol ?: $in;
		$formatted = self::trimTrailingZeros(number_format((float) $number, 2, '.', ','));

		return mb_strlen($symbol) <= 3 ? "{$symbol}{$formatted}" : "{$formatted} {$in}";
	}

	private static function trimTrailingZeros(string $number)
	{
		return rtrim(rtrim($number, '0'), '.');
	}
}
