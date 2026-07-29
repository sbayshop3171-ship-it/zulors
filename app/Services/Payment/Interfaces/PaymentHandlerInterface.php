<?php

namespace App\Services\Payment\Interfaces;

use App\Models\Payment;

interface PaymentHandlerInterface
{
	public function __construct(Payment $paymentData);
	public function handleSuccess(): void;
	public function handleFailure(): void;
	public function handleExpiration(): void;
}
