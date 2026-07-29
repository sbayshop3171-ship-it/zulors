<?php

namespace App\Services\Wallet;

use App\Models\User;
use Illuminate\Support\Str;
use App\Enums\Payment\PaymentType;
use App\Services\Payment\DTO\PaymentIntent;
use App\Services\Payment\PaymentIntentService;
use App\Services\Payment\DTO\PaymentIntentResult;

class WalletService
{
	private User $userData;

	public function setUserData(User $userData)
	{
		$this->userData = $userData;

		return $this;
	}

	public function initiateDeposit(float $amount, array $providerData): PaymentIntentResult
	{
		$paymentIntentService = new PaymentIntentService($providerData['driver']);

		$paymentIntentDTO = new PaymentIntent(
			title: __('payment.intents.deposit.title'),
			description: __('payment.intents.deposit.description', ['app_name' => config('app.name')]),
			amount: $amount,
			currency: $providerData['currency'] ?? config('app.default_currency'),
			returnUrl: route($providerData['redirect_route']),
			cancelUrl: route($providerData['cancel_route'])
		);

		$paymentIntentResult = $paymentIntentService->initiate($paymentIntentDTO);

		if ($paymentIntentResult->success) {
			// Create a new payment record if initiated successfully.
			// This is used to track the payment and handle the payment status.

			$this->userData->payments()->create([
				'payment_uuid' => Str::uuid(),
				'reference_id' => $paymentIntentResult->referenceId,
				'payment_type' => PaymentType::DEPOSIT,
				'payment_method' => $providerData['driver'],
				'amount' => $amount,
				'currency' => $providerData['currency'] ?? config('app.default_currency'),
				'metadata' => []
			]);
		}

		return $paymentIntentResult;
	}

	public function addWalletBalance(float $amount)
	{
		$this->userData->wallet->update([
			'balance' => $this->userData->wallet->balance->add($amount)
		]);

		return $this;
	}

    public function setWalletBalance(float $amount)
    {
        $this->userData->wallet->update([
            'balance' => $this->userData->wallet->balance->set($amount)
        ]);

        return $this;
    }

	public function subtractWalletBalance(float $amount)
	{
		$this->userData->wallet->update([
			'balance' => $this->userData->wallet->balance->subtract($amount)
		]);

		return $this;
	}
	public function addWalletTransaction(array $data)
	{
		return $this->userData->wallet->transactions()->create($data);

		return $this;
	}
}
