<?php

namespace App\Services\Payment\Drivers;

use Exception;
use Stripe\StripeClient;
use App\Services\Payment\DTO\PaymentIntent;
use App\Services\Payment\DTO\PaymentIntentResult;
use App\Services\Payment\Interfaces\PaymentGatewayInterface;

class StripeDriver implements PaymentGatewayInterface
{
	protected $stripeClient;

	public function __construct()
	{
		$this->stripeClient = new StripeClient(config('payment.providers.stripe.credentials.secret_key'));
	}

	public function createPaymentIntent(PaymentIntent $paymentIntent): PaymentIntentResult
	{
		$lineItems = [[
            'price_data' => [
                'currency' => $paymentIntent->currency,
                'product_data' => [
                    'name' => $paymentIntent->title,
					'description' => $paymentIntent->description,
                ],
                'unit_amount' => (int) round(((float) $paymentIntent->amount) * 100)
            ],
            'quantity' => 1
        ]];

		$sessionData = [
			'payment_method_types' => $this->paymentMethodTypes(),
			'line_items' => $lineItems,
			'mode' => 'payment',
			'success_url' => $this->appendCheckoutSessionId($paymentIntent->returnUrl),
			'cancel_url' => $paymentIntent->cancelUrl
		];

        try {
            $session = $this->stripeClient->checkout->sessions->create($sessionData);

            return new PaymentIntentResult(
				referenceId: $session->id,
				url: $session->url,
				success: true
			);
        }
        
        catch (Exception $e) {
			if ($this->isRecoverablePaymentMethodError($e) && $sessionData['payment_method_types'] !== ['card']) {
				payment_log('Stripe checkout retrying with card only: ' . $e->getMessage());

				try {
					$sessionData['payment_method_types'] = ['card'];
					$session = $this->stripeClient->checkout->sessions->create($sessionData);

					return new PaymentIntentResult(
						referenceId: $session->id,
						url: $session->url,
						success: true
					);
				}

				catch (Exception $fallbackException) {
					return new PaymentIntentResult(
						referenceId: null,
						success: false,
						message: $fallbackException->getMessage()
					);
				}
			}

			return new PaymentIntentResult(
				referenceId: null,
				success: false,
				message: $e->getMessage()
			);
        }
	}

	private function paymentMethodTypes(): array
	{
		$methodTypes = config('payment.providers.stripe.payment_method_types', ['card']);

		if (! is_array($methodTypes)) {
			$methodTypes = preg_split('/[\s,]+/', (string) $methodTypes, -1, PREG_SPLIT_NO_EMPTY);
		}

		$methodTypes = array_values(array_unique(array_filter(array_map(
			fn (string $methodType) => strtolower(trim($methodType)),
			$methodTypes
		))));

		return empty($methodTypes) ? ['card'] : $methodTypes;
	}

	private function isRecoverablePaymentMethodError(Exception $exception): bool
	{
		return str_contains(strtolower($exception->getMessage()), 'payment method type');
	}

	private function appendCheckoutSessionId(string $url): string
	{
		$separator = str_contains($url, '?') ? '&' : '?';

		return "{$url}{$separator}session_id={CHECKOUT_SESSION_ID}";
	}
}
