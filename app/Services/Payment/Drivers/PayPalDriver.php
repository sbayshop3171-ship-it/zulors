<?php

namespace App\Services\Payment\Drivers;

use Exception;
use Throwable;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use App\Services\Payment\DTO\PaymentIntent;
use App\Services\Payment\DTO\PaymentIntentResult;
use App\Services\Payment\Interfaces\PaymentGatewayInterface;

class PayPalDriver implements PaymentGatewayInterface
{
	private $clientId;
	private $secretKey;
	private $mode;
	private $baseUrl;

	public function __construct()
	{
		$this->clientId = config('payment.providers.paypal.credentials.client_id');
		$this->secretKey = config('payment.providers.paypal.credentials.secret_key');
		$this->mode = config('payment.providers.paypal.mode');
		$this->baseUrl = $this->mode === 'sandbox' ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
	}

	public function fetchAccessToken(): string
	{
		$response = Http::withBasicAuth($this->clientId, $this->secretKey)
            ->asForm()
            ->post("{$this->baseUrl}/v1/oauth2/token", [
                'grant_type' => 'client_credentials',
            ]);
		
		$response = $response->json();

		if (isset($response['access_token'])) {
			return $response['access_token'];
		}

		throw new Exception('Failed to fetch access token');
	}

	public function createPaymentIntent(PaymentIntent $paymentIntent): PaymentIntentResult
	{
		$accessToken = $this->fetchAccessToken();
		$orderPayload = $this->createOrderPayload($paymentIntent);

		$response = $this->createOrder($accessToken, $orderPayload);

		if ($this->isSchemaError($response)) {
			payment_log('PayPal checkout retrying with minimal order context.', $response);

			$response = $this->createOrder($accessToken, $this->createLegacyOrderPayload($paymentIntent));
		}

		if (isset($response['id'])) {
			$approvalLinkData = collect($response['links'] ?? [])->first(function($linkData) {
				return in_array($linkData['rel'] ?? '', ['payer-action', 'approve'], true);
			});

			if (empty($approvalLinkData['href'])) {
				return new PaymentIntentResult(
					referenceId: null,
					success: false,
					message: 'PayPal checkout link was not returned.'
				);
			}

			return new PaymentIntentResult(
				referenceId: $response['id'],
				url: $approvalLinkData['href'],
				success: true
			);
		}

		return new PaymentIntentResult(
			referenceId: null,
			success: false,
			message: $this->errorMessage($response)
		);
	}

	private function createOrder(string $accessToken, array $payload): array
	{
		return Http::withToken($accessToken)
			->acceptJson()
			->asJson()
			->withHeaders([
				'PayPal-Request-Id' => (string) Str::uuid(),
				'Prefer' => 'return=representation',
			])
			->post("{$this->baseUrl}/v2/checkout/orders", $payload)
			->json();
	}

	private function createOrderPayload(PaymentIntent $paymentIntent): array
	{
		return [
			'intent' => 'CAPTURE',
			'payment_source' => [
				'paypal' => [
					'experience_context' => [
						'brand_name' => config('app.name'),
						'payment_method_preference' => 'IMMEDIATE_PAYMENT_REQUIRED',
						'landing_page' => 'LOGIN',
						'shipping_preference' => 'NO_SHIPPING',
						'user_action' => 'PAY_NOW',
						'return_url' => $paymentIntent->returnUrl,
						'cancel_url' => $paymentIntent->cancelUrl,
					],
				],
			],
			'purchase_units' => [
				[
					'description' => $paymentIntent->description,
					'custom_id' => (string) Str::uuid(),
					'amount' => [
						'value' => number_format((float) $paymentIntent->amount, 2, '.', ''),
						'currency_code' => strtoupper($paymentIntent->currency),
					],
				],
			],
		];
	}

	private function createLegacyOrderPayload(PaymentIntent $paymentIntent): array
	{
		return [
			'intent' => 'CAPTURE',
			'purchase_units' => [
				[
					'description' => $paymentIntent->description,
					'custom_id' => (string) Str::uuid(),
					'amount' => [
						'value' => number_format((float) $paymentIntent->amount, 2, '.', ''),
						'currency_code' => strtoupper($paymentIntent->currency),
					],
				],
			],
			'application_context' => [
				'brand_name' => config('app.name'),
				'return_url' => $paymentIntent->returnUrl,
				'cancel_url' => $paymentIntent->cancelUrl,
				'shipping_preference' => 'NO_SHIPPING',
				'user_action' => 'PAY_NOW',
			],
		];
	}

	private function isSchemaError(array $response): bool
	{
		$message = strtolower($response['message'] ?? '');
		$name = strtolower($response['name'] ?? '');

		return str_contains($name, 'invalid_request') ||
			str_contains($message, 'not well-formed') ||
			str_contains($message, 'violates schema');
	}

	private function errorMessage(array $response): string
	{
		$details = collect($response['details'] ?? [])->pluck('description')->filter()->implode(' ');

		return $details ?: ($response['message'] ?? 'Failed to create payment intent in PayPal.');
	}

	public function capturePayment(string $orderId): bool
	{
		try {
			$accessToken = $this->fetchAccessToken();
			
			$response = Http::withToken($accessToken)
				->acceptJson()
				->asJson()
				->post("{$this->baseUrl}/v2/checkout/orders/{$orderId}/capture", []);

			$response = $response->json();
			
			if (isset($response['status']) && $response['status'] === 'COMPLETED') {
				return true;
			}

			return false;
		} catch (Throwable $th) {
			payment_log($th->getMessage());
			return false;
		}
	}
}
