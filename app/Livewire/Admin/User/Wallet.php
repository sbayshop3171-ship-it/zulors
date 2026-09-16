<?php

namespace App\Livewire\Admin\User;

use App\Models\User;
use App\Rules\X\XRule;
use App\Services\Ad\AdRewardService;
use App\Services\Wallet\WalletService;
use App\Support\Views\Flash;
use Livewire\Component;

class Wallet extends Component
{
    public User $userData;
    public string $walletBalance;
    public string $adsRewardCredit = '0';
    public bool $adsRewardActive = false;
    public string $walletCurrency;

    public function mount(User $userData)
    {
        $this->userData = $userData;
        $this->walletBalance = $userData->wallet->balance->getAmount();
        $this->walletCurrency = $userData->wallet->balance->getCurrency();

        $rewardAccount = $userData->adRewardAccounts()
            ->where('period_month', app(AdRewardService::class)->currentPeriod())
            ->first();

        $this->adsRewardCredit = (string) ((float) ($rewardAccount?->available_amount ?? 0));
        $this->adsRewardActive = $rewardAccount?->status === 'active';
    }

    public function render()
    {
        return view('livewire.admin.user.wallet');
    }

    public function submitForm()
    {
        $this->validate([
            'walletBalance' => ['required', 'numeric', XRule::join('min', config('wallet.deposit.min_amount')), XRule::join('max', config('wallet.deposit.max_amount'))],
            'adsRewardCredit' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'adsRewardActive' => ['nullable', 'bool'],
        ], attributes: [
            'walletBalance' => __('admin/users.form.wallet_balance'),
            'adsRewardCredit' => __('admin/users.form.ads_reward_credit'),
        ]);

        $newBalance = floatval($this->walletBalance);

        if($this->userData->wallet->balance->getAmount() != $newBalance) {
            // TODO: Add transaction history here + notification to user.

            $walletService = new WalletService();
            $walletService->setUserData($this->userData);
            $walletService->setWalletBalance($newBalance);
        }

        $adRewardService = app(AdRewardService::class);

        if($this->userData->isVerified() && $adRewardService->isEnabled()) {
            $adRewardService->setCurrentCredit($this->userData, (float) $this->adsRewardCredit);
            $adRewardService->setCurrentStatus($this->userData, (bool) $this->adsRewardActive);
        }

        return redirect()->with('flashMessage', (new Flash(content: __('admin/flash.user.wallet_balance_success', ['amount' => $newBalance])))->get())
            ->route('admin.users.wallet', $this->userData->id);
    }
}
