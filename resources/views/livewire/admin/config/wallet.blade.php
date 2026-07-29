<form wire:submit.prevent="submitForm">
    @csrf

    <x-form.group>
        <x-form.text-input
            labelText="{{ __('admin/wallet.form.name') }}"
            wire:model="formData.name"
            type="text"
        name="formData.name">
            <x-slot:feedbackInfo>
                {{ __('admin/wallet.form.name_helper') }}
            </x-slot:feedbackInfo>
        </x-form.text-input>
    </x-form.group>
    <x-form.group>
        <x-form.switcher
            labelText="{{ __('admin/config.form.features_status') }}"
            wire:model="formData.enabled"
            name="formData.enabled">
        </x-form.switcher>
    </x-form.group>
    <x-form.group>
        <x-form.text-input
            labelText="{{ __('admin/wallet.form.about_link') }}"
            wire:model="formData.about_link"
            type="text"
        name="formData.about_link">
            <x-slot:feedbackInfo>
                {{ __('admin/wallet.form.about_link_helper') }}
            </x-slot:feedbackInfo>
        </x-form.text-input>
    </x-form.group>

    <x-form.group cols="grid-cols-2">
        <x-form.text-input
            labelText="{{ __('admin/wallet.form.default_balance') }}"
            wire:model="formData.default_balance"
            type="number"
            name="formData.default_balance">
        </x-form.text-input>
        <x-form.text-input
            labelText="{{ __('admin/wallet.form.wallet_number_prefix') }}"
            wire:model="formData.wallet_number_prefix"
            type="text"
            name="formData.wallet_number_prefix">
        </x-form.text-input>
    </x-form.group>
    <x-form.group cols="grid-cols-2">
        <x-form.text-input
            labelText="{{ __('admin/wallet.form.deposit_min_amount') }}"
            wire:model="formData.deposit_min_amount"
            type="number"
            name="formData.deposit_min_amount">
        </x-form.text-input>
        <x-form.text-input
            labelText="{{ __('admin/wallet.form.deposit_max_amount') }}"
            wire:model="formData.deposit_max_amount"
            type="number"
            name="formData.deposit_max_amount">
        </x-form.text-input>
    </x-form.group>
    <x-form.group cols="grid-cols-2">
        <x-form.text-input
            labelText="{{ __('admin/wallet.form.transfer_min_amount') }}"
            wire:model="formData.transfer_min_amount"
            type="number"
            name="formData.transfer_min_amount">
        </x-form.text-input>
        <x-form.text-input
            labelText="{{ __('admin/wallet.form.deposit_max_amount') }}"
            wire:model="formData.transfer_max_amount"
            type="number"
            name="formData.transfer_max_amount">
        </x-form.text-input>
    </x-form.group>
    <x-form.group cols="grid-cols-2">
        <x-form.text-input
            labelText="{{ __('admin/wallet.form.commission_deposit') }}"
            wire:model="formData.commission_deposit"
            type="number"
            name="formData.commission_deposit">
        </x-form.text-input>
        <x-form.text-input
            labelText="{{ __('admin/wallet.form.commission_transfer') }}"
            wire:model="formData.commission_transfer"
            type="number"
            name="formData.commission_transfer">
        </x-form.text-input>
    </x-form.group>

    <x-form.group>
        <x-form.text-input
            labelText="{{ __('admin/wallet.form.cashout_methods') }}"
            wire:model="formData.cashout_methods"
            :asText="true"
            placeholder="E.g. Bank Transfer, PayPal, Stripe"
            name="formData.cashout_methods">
            <x-slot:feedbackInfo>
                {{ __('admin/wallet.form.cashout_methods_helper') }}
            </x-slot:feedbackInfo>
        </x-form.text-input>
    </x-form.group>
    <x-ui.buttons.pill size="sm" type="submit" btnText="{{ __('buttons.save_changes') }}"></x-ui.buttons.pill>
</form>
