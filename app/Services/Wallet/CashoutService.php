<?php

namespace App\Services\Wallet;

use App\Enums\Wallet\CashoutStatus;
use App\Models\Cashout;
use App\Models\User;
use App\Services\Wallet\WalletService;
use InvalidArgumentException;

class CashoutService
{
    private User $userData;

    public function __construct(int|User $user)
    {
        $this->userData = ($user instanceof User) ? $user : User::activeById($user)->first();

        if (!$this->userData) {
            throw new InvalidArgumentException('User not found or is not active.');
        }

        return $this;
    }

    public function isPendingRequestExists(): bool
    {
        return $this->userData->cashouts()->pending()->exists();
    }

    public function createCashout(array $data): Cashout
    {
        $data['request_code'] = random_int($this->userData->id,  $this->userData->id + 999999);
        $data['commission_percentage'] = config('wallet.commission.withdraw');
        $data['to_pay'] = calculate_commission($data['amount'], $data['commission_percentage']);

        return $this->userData->cashouts()->create($data);
    }

    public function returnAmountToWallet(float $amount): void
    {
        (new WalletService())
            ->setUserData($this->userData)
            ->addWalletBalance($amount);
    }

    public function rejectCashout(int $cashoutId): void
    {
        $this->userData->cashouts()->where('id', $cashoutId)->update([
            'status' => CashoutStatus::REJECTED
        ]);
    }

    public function markAsPaid(int $cashoutId): void
    {
        $this->userData->cashouts()->where('id', $cashoutId)->update([
            'status' => CashoutStatus::PAID
        ]);
    }
}
