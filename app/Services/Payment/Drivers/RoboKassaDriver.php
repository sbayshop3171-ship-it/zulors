<?php

namespace App\Services\Payment\Drivers;

use App\Services\Payment\DTO\PaymentIntent;
use App\Services\Payment\DTO\PaymentIntentResult;
use App\Services\Payment\Interfaces\PaymentGatewayInterface;
use Illuminate\Support\Str;

class RoboKassaDriver implements PaymentGatewayInterface
{
	private $merchantLogin;
	private $passOne;
	private $mode;

	public function __construct()
	{
		$this->merchantLogin = config('payment.providers.robokassa.credentials.merchant_login');
		$this->passOne = config('payment.providers.robokassa.credentials.pass_one');
		$this->mode = config('payment.providers.robokassa.mode');
	}

	public function createPaymentIntent(PaymentIntent $paymentIntent): PaymentIntentResult
	{
		$referenceId = $this->generateReferenceId();
		$crc = md5("{$this->merchantLogin}:{$paymentIntent->amount}:{$referenceId}:{$this->passOne}");

		$paymentUrl = "https://auth.robokassa.ru/Merchant/Index.aspx?" . http_build_query([
			'MerchantLogin' => $this->merchantLogin,
			'OutSum' => $paymentIntent->amount,
			'InvId' => $referenceId,
			'Description' => $paymentIntent->description,
			'SignatureValue' => $crc,
			'Culture' => 'ru',
			'IsTest' => $this->mode === 'sandbox' ? '1' : '0',
			'Encoding' => 'utf-8'
		]);

		return new PaymentIntentResult(
			referenceId: $referenceId,
			url: $paymentUrl,
			success: true
		);
	}

	private function generateReferenceId(): string
	{
		$min = 1;
		$max = 2147483647;

		$id = random_int($min, $max);
		return str_pad((string)$id, 10, '0', STR_PAD_LEFT);
	}
}
