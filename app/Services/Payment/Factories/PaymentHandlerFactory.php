<?php

namespace App\Services\Payment\Factories;

use Exception;
use App\Models\Payment;
use App\Enums\Payment\PaymentType;
use App\Services\Payment\Handlers\DepositPaymentHandler;
use App\Services\Payment\Interfaces\PaymentHandlerInterface;

class PaymentHandlerFactory
{
	public static function make(Payment $paymentData): PaymentHandlerInterface
	{
		return match ($paymentData->payment_type) {
			PaymentType::DEPOSIT => new DepositPaymentHandler($paymentData),
			default => throw new Exception("Count not find handler for payment type: {$paymentData->payment_type}")
		};
	}
}
