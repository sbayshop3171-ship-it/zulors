<form wire:submit.prevent="submitForm">
    <div class="mb-8">
        <x-entity.header avatarUrl="{{ asset($providerInfo['meta']['logo']) }}" name="{{ $providerInfo['meta']['name'] }}"></x-entity.header>
    </div>
    @csrf

    <x-form.group>
        <x-form.text-input
            labelText="{{ __('admin/config.form.client_id') }}"
            wire:model="formData.client_id"
            type="password"
            :isPassword="true"
        name="formData.client_id">
        </x-form.text-input>
    </x-form.group>
    <x-form.group>
        <x-form.text-input
            labelText="{{ __('admin/config.form.client_secret') }}"
            wire:model="formData.client_secret"
            type="password"
            :isPassword="true"
        name="formData.client_secret">
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
            labelText="{{ __('admin/config.form.redirect_url') }}"
            value="{{ $providerInfo['credentials']['redirect'] }}"
            type="text"
            :isReadOnly="true">
            <x-slot:feedbackInfo>
                {{ __('admin/config.form.redirect_url_helper') }}
            </x-slot:feedbackInfo>
        </x-form.text-input>
    </x-form.group>

    <x-ui.buttons.pill size="sm" type="submit" btnText="{{ __('buttons.save_changes') }}"></x-ui.buttons.pill>
</form>
