<?php

namespace App\Services\Payment\Factories;

use Exception;
use App\Services\Payment\Drivers\StripeDriver;
use App\Services\Payment\Drivers\PayPalDriver;
use App\Services\Payment\Interfaces\PaymentGatewayInterface;
use App\Services\Payment\Drivers\YooKassaDriver;
use App\Services\Payment\Drivers\RoboKassaDriver;

class PaymentGatewayFactory
{
	public static function make(string $driverName): PaymentGatewayInterface
	{
		return match ($driverName) {
			'stripe' => new StripeDriver(),
			'paypal' => new PayPalDriver(),
			'yoo_kassa', 'yookassa' => new YooKassaDriver(),
			'robokassa' => new RoboKassaDriver(),
			default => throw new Exception("Invalid driver name: {$driverName}"),
		};
	}
}
