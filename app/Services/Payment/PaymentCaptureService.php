<?php

namespace App\Services\Payment;

use App\Services\Payment\Factories\PaymentGatewayFactory;
use App\Services\Payment\Interfaces\PaymentGatewayInterface;

class PaymentCaptureService
{
	private PaymentGatewayInterface $driver;

	public function __construct(string $driverName)
	{
		$this->driver = PaymentGatewayFactory::make($driverName);
	}

	public function capturePayment(string $orderId): bool
	{
		return $this->driver->capturePayment($orderId);
	}
}
