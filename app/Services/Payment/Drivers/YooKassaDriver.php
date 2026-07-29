<?php

namespace App\Services\Payment\Drivers;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use App\Services\Payment\DTO\PaymentIntent;
use App\Services\Payment\DTO\PaymentIntentResult;
use App\Services\Payment\Interfaces\PaymentGatewayInterface;

class YooKassaDriver implements PaymentGatewayInterface
{
	private $shopId;
	private $secretKey;

	public function __construct()
	{
		$this->shopId = config('payment.providers.yoo_kassa.credentials.shop_id');
		$this->secretKey = config('payment.providers.yoo_kassa.credentials.secret_key');
	}

	public function createPaymentIntent(PaymentIntent $paymentIntent): PaymentIntentResult
	{
		$payload = [
			'amount' => [
				'value' => $paymentIntent->amount,
				'currency' => $paymentIntent->currency,
			],
			'confirmation' => [
				'type' => 'redirect',
				'return_url' => $paymentIntent->returnUrl,
			],
			'capture' => true,
			'description' => $paymentIntent->description
		];

		$idempotenceKey = Str::uuid()->toString();

		$response = Http::withBasicAuth($this->shopId, $this->secretKey)
			->withHeaders([
				'Content-Type' => 'application/json',
				'Idempotence-Key' => $idempotenceKey,
			])
			->post('https://api.yookassa.ru/v3/payments', $payload);

		$isFailed = $response->failed();
		$response = $response->json();

		if ($isFailed) {
			return new PaymentIntentResult(
				referenceId: null,
				success: false,
				message: $response['description'] ?? 'Failed to create payment intent in YooKassa.'
			);
		}

		return new PaymentIntentResult(
			referenceId: $response['id'],
			url: $response['confirmation']['confirmation_url'],
			success: true
		);
	}
}