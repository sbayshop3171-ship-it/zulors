<?php

namespace App\Livewire\Admin\Config;

use App\Settings\WalletSettings;
use App\Support\Views\Flash;
use Livewire\Component;

class Wallet extends Component
{
    public array $formData = [];

    public function mount()
    {
        $walletSettings = app(WalletSettings::class);

        $this->formData = [
            'name' => $walletSettings->name,
            'about_link' => $walletSettings->about_link,
            'wallet_number_prefix' => $walletSettings->wallet_number_prefix,
            'default_balance' => $walletSettings->default_balance,
            'deposit_min_amount' => $walletSettings->deposit_min_amount,
            'deposit_max_amount' => $walletSettings->deposit_max_amount,
            'transfer_min_amount' => $walletSettings->transfer_min_amount,
            'transfer_max_amount' => $walletSettings->transfer_max_amount,
            'commission_deposit' => $walletSettings->commission_deposit,
            'commission_transfer' => $walletSettings->commission_transfer,
            'cashout_methods' => $walletSettings->cashout_methods,
            'enabled' => $walletSettings->enabled
        ];
    }

    public function submitForm()
    {
        $this->validate([
            'formData.name' => ['required', 'string', 'max:255'],
            'formData.enabled' => ['nullable', 'bool'],
            'formData.about_link' => ['nullable', 'string', 'max:255'],
            'formData.wallet_number_prefix' => ['required', 'uppercase', 'size:3'],
            'formData.default_balance' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'formData.deposit_min_amount' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'formData.deposit_max_amount' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'formData.transfer_min_amount' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'formData.transfer_max_amount' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'formData.commission_deposit' => ['required', 'numeric', 'min:0', 'max:100'],
            'formData.commission_transfer' => ['required', 'numeric', 'min:0', 'max:100'],
            'formData.cashout_methods' => ['required', 'string', 'max:1200'],
        ], attributes: [
            'formData.name' => __('admin/wallet.form.name'),
            'formData.about_link' => __('admin/wallet.form.about_link'),
            'formData.wallet_number_prefix' => __('admin/wallet.form.wallet_number_prefix'),
            'formData.default_balance' => __('admin/wallet.form.default_balance'),
            'formData.deposit_min_amount' => __('admin/wallet.form.deposit_min_amount'),
            'formData.deposit_max_amount' => __('admin/wallet.form.deposit_max_amount'),
            'formData.transfer_min_amount' => __('admin/wallet.form.transfer_min_amount'),
            'formData.transfer_max_amount' => __('admin/wallet.form.transfer_max_amount'),
            'formData.commission_deposit' => __('admin/wallet.form.commission_deposit'),
            'formData.commission_transfer' => __('admin/wallet.form.commission_transfer'),
            'formData.cashout_methods' => __('admin/wallet.form.cashout_methods'),
        ]);

        $walletSettings = app(WalletSettings::class);

        $walletSettings->name = $this->formData['name'];
        $walletSettings->enabled = $this->formData['enabled'] ?? true;
        $walletSettings->about_link = $this->formData['about_link'];
        $walletSettings->wallet_number_prefix = $this->formData['wallet_number_prefix'];
        $walletSettings->default_balance = $this->formData['default_balance'];
        $walletSettings->deposit_min_amount = $this->formData['deposit_min_amount'];
        $walletSettings->deposit_max_amount = $this->formData['deposit_max_amount'];
        $walletSettings->transfer_min_amount = $this->formData['transfer_min_amount'];
        $walletSettings->transfer_max_amount = $this->formData['transfer_max_amount'];
        $walletSettings->commission_deposit = $this->formData['commission_deposit'];
        $walletSettings->commission_transfer = $this->formData['commission_transfer'];
        $walletSettings->cashout_methods = $this->formData['cashout_methods'];
        $walletSettings->save();

        return redirect()->with('flashMessage', (new Flash(content: __('admin/flash.config.settings_success')))->get())
            ->route('admin.config.wallet');
    }

    public function render()
    {
        return view('livewire.admin.config.wallet');
    }
}
