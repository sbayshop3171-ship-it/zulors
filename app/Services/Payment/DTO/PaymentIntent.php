<?php

namespace App\Services\Payment\DTO;

class PaymentIntent
{
	public string $title;
	public string $description;
	public string $amount;
	public string $currency;
	public string $returnUrl;
	public string $cancelUrl;
	public array $metadata = [];

	public function __construct(string $title, string $description, string $amount, string $currency, string $returnUrl, string $cancelUrl, array $metadata = [])
	{
		$this->title = $title;
		$this->description = $description;
		$this->amount = $amount;
		$this->currency = $currency;
		$this->returnUrl = $returnUrl;
		$this->cancelUrl = $cancelUrl;
		$this->metadata = $metadata;
	}
}
