<?php

namespace App\Services\Payment\Interfaces;

use App\Services\Payment\DTO\PaymentIntent;
use App\Services\Payment\DTO\PaymentIntentResult;

interface PaymentGatewayInterface
{
	public function createPaymentIntent(PaymentIntent $paymentIntent): PaymentIntentResult;
}
