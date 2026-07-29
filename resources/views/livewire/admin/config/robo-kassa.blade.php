<form wire:submit.prevent="submitForm" enctype="multipart/form-data">
    <div class="mb-8">
        <x-entity.header avatarUrl="{{ asset($providerInfo['logo']) }}" name="{{ $providerInfo['name'] }}"></x-entity.header>
    </div>
    @csrf
    <x-form.group>
        <x-form.text-input
            labelText="{{ __('admin/config.form.provider_name') }}"
            type="text"
            wire:model="formData.name"
            name="formData.name">
        </x-form.text-input>
    </x-form.group>
    <x-form.group>
        <x-form.switcher
            labelText="{{ __('admin/config.form.provider_status') }}"
            wire:model="formData.status"
            name="formData.status">
        </x-form.switcher>
    </x-form.group>

    <x-form.group>
        <x-form.text-input
            labelText="{{ __('admin/config.form.merchant_login') }}"
            type="text"
            wire:model="formData.merchant_login"
            name="formData.merchant_login">
        </x-form.text-input>
    </x-form.group>
    <x-form.group>
        <x-form.text-input
            labelText="{{ __('admin/config.form.pass_one') }}"
            type="password"
            :isPassword="true"
            wire:model="formData.pass_one"
            name="formData.pass_one">
        </x-form.text-input>
    </x-form.group>
    <x-form.group>
        <x-form.text-input
            labelText="{{ __('admin/config.form.pass_two') }}"
            type="password"
            :isPassword="true"
            wire:model="formData.pass_two"
            name="formData.pass_two">
        </x-form.text-input>
    </x-form.group>
    <x-form.group>
        <x-form.text-input
            labelText="{{ __('admin/config.form.acquiring_currency') }}"
            type="text"
            wire:model="formData.currency"
            name="formData.currency">
            <x-slot:feedbackInfo>
                {{ __('admin/config.form.acquiring_currency_helper') }}
            </x-slot:feedbackInfo>
        </x-form.text-input>
    </x-form.group>

    <x-ui.buttons.pill size="sm" type="submit" btnText="{{ __('buttons.save_changes') }}"></x-ui.buttons.pill>
</form>
