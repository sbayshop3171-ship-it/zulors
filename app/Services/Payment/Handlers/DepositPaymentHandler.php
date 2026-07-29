<?php

namespace App\Services\Payment\Handlers;

use Throwable;
use App\Models\Payment;
use App\Enums\Payment\PaymentStatus;
use App\Enums\Wallet\TransactionType;
use App\Services\Wallet\WalletService;
use App\Enums\Wallet\TransactionStatus;
use App\Enums\Wallet\TransactionDirection;
use App\Services\Payment\Interfaces\PaymentHandlerInterface;
use App\Notifications\System\Deposit\DepositSuccessNotification;

class DepositPaymentHandler implements PaymentHandlerInterface
{
	private Payment $paymentData;

	public function __construct(Payment $paymentData)
	{
		$this->paymentData = $paymentData;
	}

	public function handleSuccess(): void
	{
		try {
			if ($this->paymentData->status->isPending()) {
				$walletService = app(WalletService::class);
				$walletService
					->setUserData($this->paymentData->user)
					->addWalletBalance($this->paymentData->amount)
					->addWalletTransaction([
						'amount' => $this->paymentData->amount,
						'transaction_type' => TransactionType::DEPOSIT,
						'status' => TransactionStatus::COMPLETED,
						'direction' => TransactionDirection::INCOMING,
						'currency' => $this->paymentData->currency,
						'metadata' => [
							'reference_id' => $this->paymentData->reference_id,
							'source' => [
								'name' => $this->paymentData->provider_name
							]
						]
					]);
				
				$this->paymentData->user->notify(new DepositSuccessNotification());
	
				$this->paymentData->update([
					'status' => PaymentStatus::COMPLETED
				]);
			}
		} catch (Throwable $th) {
			payment_log($th->getMessage());
		}
	}

	public function handleFailure(): void
	{
		$this->paymentData->status = PaymentStatus::FAILED;
		$this->paymentData->save();

		payment_log('Payment with id ' . $this->paymentData->id . ' failed');
	}

	public function handleRefund(): void
	{
		try {
			if ($this->paymentData->status->isCompleted()) {	
				$walletService = app(WalletService::class);
				$walletService->setUserData($this->paymentData->user)
					->subtractWalletBalance($this->paymentData->amount)
					->addWalletTransaction([
						'amount' => $this->paymentData->amount,
						'transaction_type' => TransactionType::REFUND,
						'status' => TransactionStatus::COMPLETED,
						'direction' => TransactionDirection::OUTGOING,
						'currency' => $this->paymentData->currency,
						'metadata' => [
							'reference_id' => $this->paymentData->reference_id,
							'source' => ['name' => $this->paymentData->provider_name]
						]
					]);
			}

			// TODO: Notify user about refund
		} catch (Throwable $th) {
			payment_log($th->getMessage());
		}
	}

	public function handleExpiration(): void
	{
		$this->paymentData->status = PaymentStatus::EXPIRED;
		$this->paymentData->save();

		payment_log('Payment with id ' . $this->paymentData->id . ' expired');
	}
}