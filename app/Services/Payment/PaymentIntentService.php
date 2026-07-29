<?php

namespace App\Services\Payment;

use App\Services\Payment\DTO\PaymentIntent;
use App\Services\Payment\DTO\PaymentIntentResult;
use App\Services\Payment\Factories\PaymentGatewayFactory;
use App\Services\Payment\Interfaces\PaymentGatewayInterface;

class PaymentIntentService
{	
	/**
	 * This class is responsible for initiating payments based on the provider and payment purpose.
	 * It accepts a driver name and a payment intent DTO.
	 * It calls the appropriate driver to create a payment intent. PayPay, Stripe, etc.
	 * The driver is selected based on the provider name and it interacts with the external payment provider,
	 * to create a payment session and retrieve necessary data like the payment url, ID, etc.
	 * All drivers implement the PaymentGatewayInterface and must return a PaymentIntentResult object.
	 * 
	 * @param string $driverName
	 * @param PaymentIntent $paymentIntent
	 * @return PaymentIntentResult
	 */

	private PaymentGatewayInterface $driver;

	public function __construct(string $driverName)
	{
		$this->driver = PaymentGatewayFactory::make($driverName);
	}

	public function initiate(PaymentIntent $paymentIntent): PaymentIntentResult
	{	
		return $this->driver->createPaymentIntent($paymentIntent);
	}
}
