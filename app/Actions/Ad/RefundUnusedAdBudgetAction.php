<?php

namespace App\Actions\Ad;

use App\Models\Ad;
use App\Enums\Wallet\TransactionType;
use App\Services\Wallet\WalletService;
use App\Enums\Wallet\TransactionStatus;
use App\Enums\Wallet\TransactionDirection;

class RefundUnusedAdBudgetAction
{
    public function __construct(private Ad $adData)
    {
    }

    public function execute(): float
    {
        $refundAmount = $this->getRefundAmount();

        if($refundAmount <= 0) {
            return 0;
        }

        $userData = $this->adData->user()->with('wallet')->first();

        if(empty($userData?->wallet)) {
            return 0;
        }

        app(WalletService::class)
            ->setUserData($userData)
            ->addWalletBalance($refundAmount)
            ->addWalletTransaction([
                'amount' => $refundAmount,
                'transaction_type' => TransactionType::REFUND,
                'status' => TransactionStatus::COMPLETED,
                'direction' => TransactionDirection::INCOMING,
                'currency' => config('app.default_currency'),
                'metadata' => [
                    'ad_id' => $this->adData->id,
                    'source' => ['name' => config('ads.name')],
                    'reason' => 'unused_ad_budget',
                    'total_budget' => (float) $this->adData->total_budget,
                    'spent_budget' => (float) $this->adData->spent_budget,
                ]
            ]);

        return $refundAmount;
    }

    private function getRefundAmount(): float
    {
        return max(0, round(((float) $this->adData->total_budget - (float) $this->adData->spent_budget), 2));
    }
}
