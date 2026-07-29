<?php

namespace App\Services\Payment;

use Exception;
use App\Models\Payment;
use App\Services\Payment\Factories\PaymentHandlerFactory;
use App\Services\Payment\Interfaces\PaymentHandlerInterface;

class PaymentProcessService
{
	private Payment $paymentData;

	public function fetchPaymentData(string $referenceId): void
	{
		$paymentData = Payment::where('reference_id', $referenceId)->first();

		if (! $paymentData) {
			throw new Exception("Payment with reference ID {$referenceId} not found in the database.");
		}

		$this->paymentData = $paymentData;
	}

	public function getHandler(string $referenceId): PaymentHandlerInterface
	{
		$this->fetchPaymentData($referenceId);

		return PaymentHandlerFactory::make($this->paymentData);
	}
}
