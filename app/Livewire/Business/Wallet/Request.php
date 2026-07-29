<?php

namespace App\Livewire\Business\Wallet;

use App\Rules\X\XRule;
use App\Services\Wallet\CashoutService;
use App\Services\Wallet\WalletService;
use App\Support\Views\Flash;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Request extends Component
{
    public array $formData = [];
    public array $cashoutMethods = [];

    public function mount()
    {
        $this->cashoutMethods = collect(config('wallet.cashout.methods'))->map(function($method) {
            return [
                'key' => $method,
                'value' => $method,
            ];
        })->toArray();

        $this->formData = [
            'amount' => me()->wallet->balance->getAmount(),
            'payment_method' => null,
            'credentials' => null,
            'reviewer_notes' => null,
        ];
    }

    public function render()
    {
        return view('livewire.business.wallet.request');
    }

    public function submitForm()
    {
        $this->validate([
            'formData.amount' => [
                'required',
                'numeric',
                XRule::join('min', config('wallet.withdraw.min_amount')),
                XRule::join('max', config('wallet.withdraw.max_amount'))
            ],
            'formData.payment_method' => ['required', 'string', Rule::in(array_column($this->cashoutMethods, 'key'))],
            'formData.credentials' => ['required', 'string', 'min:10', 'max:1200'],
            'formData.reviewer_notes' => ['nullable', 'string', 'max:1200'],
        ], attributes: [
            'formData.amount' => __('business/wallet.form.amount'),
            'formData.payment_method' => __('business/wallet.form.payment_method'),
            'formData.credentials' => __('business/wallet.form.credentials'),
            'formData.reviewer_notes' => __('business/wallet.form.reviewer_notes'),
        ]);

        $cashoutService = new CashoutService(me());
        $walletService = (new WalletService())->setUserData(me());

        if (! me()->wallet->balance->canAfford($this->formData['amount'])) {
            $this->addError('formData.amount', __('business/wallet.validation.amount.insufficient_balance'));
        }
        else if ($cashoutService->isPendingRequestExists()) {
            $this->addError('formData.amount', __('business/wallet.validation.amount.pending_request'));
        }
        else {
            $cashoutService->createCashout($this->formData);
            $walletService->subtractWalletBalance($this->formData['amount']);

            return redirect()->route('business.wallet.index')->with('flashMessage', (new Flash(content: __('business/flash.wallet.request_success')))->get());
        }
    }
}
