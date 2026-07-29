<?php

namespace App\Support\VO;

use App\Support\Num;
use InvalidArgumentException;

class Balance implements ValueObjectInterface
{
	private float $amount;
	private string $currency;

	public function __construct(float $amount, string $currency)
	{
		if(! $this->validateAmount($amount)) {
			throw new InvalidArgumentException('Amount must be greater than 0');
		}

		if(! $this->validateCurrency($currency)) {
			throw new InvalidArgumentException('Currency must be a string');
		}

		$this->amount = $amount;
		$this->currency = $currency;
	}

	private function validateAmount(float $amount): bool
	{
		return $amount >= 0;
	}

	private function validateCurrency(string $currency): bool
	{
		return is_string($currency);
	}

	public function equals(ValueObjectInterface $valueObject): bool
	{
		return true;
	}

	public function getAmount(): float
	{
		return $this->amount;
	}

	public function getFormattedAmount(): string
	{
		return Num::currency($this->amount, $this->currency);
	}

	public function getCurrency(): string
	{
		return $this->currency;
	}

	public function add(float $amount): Balance
	{
		$amount = bcadd($this->amount, $amount);
		return new self($amount, $this->currency);
	}

	public function set(float $amount): Balance
	{
		return new self($amount, $this->currency);
	}

	public function subtract(float $amount): Balance
	{
		$newAmount = bcsub($this->amount, $amount);

		if ($newAmount < 0) {
			$newAmount = 0;
		}

		return new self($newAmount, $this->currency);
	}

	public function canAfford(float $amount): bool
	{
		$comparison = bccomp($this->amount, $amount);
		return $comparison === 1 || $comparison === 0;
	}
}
