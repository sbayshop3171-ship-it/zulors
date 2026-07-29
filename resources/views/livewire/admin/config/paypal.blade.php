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
            labelText="{{ __('admin/config.form.client_id') }}"
            type="password"
            :isPassword="true"
            wire:model="formData.client_id"
            name="formData.client_id">
            <x-slot:feedbackInfo>
                {{ __('admin/config.form.client_id_helper') }}
            </x-slot:feedbackInfo>
        </x-form.text-input>
    </x-form.group>
    <x-form.group>
        <x-form.text-input
            labelText="{{ __('admin/config.form.secret_key') }}"
            type="password"
            :isPassword="true"
            wire:model="formData.secret_key"
            name="formData.secret_key">
        </x-form.text-input>
    </x-form.group>

    <x-form.group>
        <x-form.radio-group labelText="{{ __('admin/config.form.mode') }}">
            <x-form.radio
                name="formData.mode"
                wire:model="formData.mode"
                defaultValue="sandbox"
                labelText="{{ __('admin/config.form.mode_sandbox') }}">
            </x-form.radio>
            <x-form.radio
                name="formData.mode"
                wire:model="formData.mode"
                defaultValue="live"
                labelText="{{ __('admin/config.form.mode_live') }}">
            </x-form.radio>
        </x-form.radio-group>

        <x-form.helper-text>
            {{ __('admin/config.form.mode_helper') }}
        </x-form.helper-text>
    </x-form.group>

    <x-ui.buttons.pill size="sm" type="submit" btnText="{{ __('buttons.save_changes') }}"></x-ui.buttons.pill>
</form>
