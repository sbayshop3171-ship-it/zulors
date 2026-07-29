<?php

namespace App\Support;

class Money
{
	public static function add(float $amountOne, float $amountTwo): float
	{
		return bcadd($amountOne, $amountTwo);
	}

	public static function subtract(float $amountOne, float $amountTwo): float
	{
		return bcsub($amountOne, $amountTwo);
	}
}
