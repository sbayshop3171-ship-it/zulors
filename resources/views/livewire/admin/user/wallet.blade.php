<form wire:submit.prevent="submitForm" enctype="multipart/form-data">
    @csrf
    <x-form.group>
        <x-form.text-input
            labelText="{{ __('admin/users.form.wallet_balance') }}, {{ $walletCurrency }}"
            type="text"
            wire:model="walletBalance"
            name="walletBalance">
            <x-slot:feedbackInfo>
                {{ __('admin/users.form.wallet_balance_helper') }}
            </x-slot:feedbackInfo>
        </x-form.text-input>
    </x-form.group>
    <x-form.group>
        <x-form.text-input
            labelText="{{ __('admin/users.form.ads_reward_credit') }}, {{ $walletCurrency }}"
            type="text"
            wire:model="adsRewardCredit"
            name="adsRewardCredit">
            <x-slot:feedbackInfo>
                {{ __('admin/users.form.ads_reward_credit_helper') }}
            </x-slot:feedbackInfo>
        </x-form.text-input>
    </x-form.group>
    <x-form.group>
        <x-form.switcher
            labelText="{{ __('admin/users.form.ads_reward_active') }}"
            wire:model="adsRewardActive"
            name="adsRewardActive">
        </x-form.switcher>
    </x-form.group>

    <x-ui.buttons.pill size="sm" type="submit" btnText="{{ __('buttons.save_changes') }}"></x-ui.buttons.pill>
</form>
