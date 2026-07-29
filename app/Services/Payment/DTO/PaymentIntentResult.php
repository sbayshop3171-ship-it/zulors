<?php

namespace App\Services\Payment\DTO;

class PaymentIntentResult
{
	public string|null $referenceId;
	public bool $success;
	public string $message;
	public string $url;
	public array $data;

	/**
	 * Create a new PaymentIntentResult instance.
	 * 
	 * @param string|null $referenceId
	 * @param bool $success
	 * @param string $url
	 * @param string $message
	 * @param array $data
	 */

	public function __construct(string|null $referenceId, bool $success, string $url = '', string $message = '', array $data = [])
	{
		$this->referenceId = $referenceId;
		$this->success = $success;
		$this->url = $url;
		$this->message = $message;
		$this->data = $data;
	}

	/**
	 * Check if the payment is a hosted checkout.
	 * 
	 * @return bool
	 */

	public function isHostedCheckout(): bool
	{
		return ! empty($this->url);
	}
}
