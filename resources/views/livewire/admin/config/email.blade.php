<form wire:submit.prevent="submitForm" enctype="multipart/form-data">
    @csrf
    <x-form.group>
        <x-form.text-input
            labelText="{{ __('admin/config.form.email_transport') }}"
            type="text"
            readonly
            wire:model="formData.transport"
            name="formData.transport">
            <x-slot:feedbackInfo>
                {{ __('admin/config.form.email_transport_helper', ['app_name' => config('app.name')]) }}
            </x-slot:feedbackInfo>
        </x-form.text-input>
    </x-form.group>
    <x-form.group>
        <x-form.text-input
            labelText="{{ __('admin/config.form.host') }}"
            type="text"
            wire:model="formData.host"
            name="formData.host">
            <x-slot:feedbackInfo>
                {{ __('admin/config.form.host_helper', ['app_name' => config('app.name')]) }}
            </x-slot:feedbackInfo>
        </x-form.text-input>
    </x-form.group>
    <x-form.group>
        <x-form.text-input
            labelText="{{ __('admin/config.form.local_domain') }}"
            type="text"
            wire:model="formData.local_domain"
            name="formData.local_domain">
            <x-slot:feedbackInfo>
                {{ __('admin/config.form.local_domain_helper') }}
            </x-slot:feedbackInfo>
        </x-form.text-input>
    </x-form.group>
    <x-form.group cols="grid-cols-2">
        <x-form.text-input
            labelText="{{ __('admin/config.form.port') }}"
            type="number"
            wire:model="formData.port"
            name="formData.port">
        </x-form.text-input>
        <x-form.text-input
            labelText="{{ __('admin/config.form.timeout') }}"
            type="number"
            wire:model="formData.timeout"
            name="formData.timeout">
        </x-form.text-input>
    </x-form.group>
    <x-form.group cols="grid-cols-2">
        <x-form.text-input
            labelText="{{ __('admin/config.form.username') }}"
            type="text"
            wire:model="formData.username"
            name="formData.username">
        </x-form.text-input>
        <x-form.text-input
            :isPassword="true"
            labelText="{{ __('admin/config.form.password') }}"
            type="password"
            wire:model="formData.password"
            name="formData.password">
        </x-form.text-input>
    </x-form.group>
    <x-form.group>
        <x-form.text-input
            labelText="{{ __('admin/config.form.encryption') }}"
            type="text"
            wire:model="formData.encryption"
            name="formData.encryption">
            <x-slot:feedbackInfo>
                {{ __('admin/config.form.encryption_helper') }}
            </x-slot:feedbackInfo>
        </x-form.text-input>
    </x-form.group>
    <x-form.group>
        <x-form.text-input
            labelText="{{ __('admin/config.form.from_address') }}"
            type="email"
            wire:model="formData.from_address"
            name="formData.from_address">
            <x-slot:feedbackInfo>
                {{ __('admin/config.form.from_address_helper') }}
            </x-slot:feedbackInfo>
        </x-form.text-input>
    </x-form.group>
    <x-form.group>
        <x-form.text-input
            labelText="{{ __('admin/config.form.from_name') }}"
            type="text"
            wire:model="formData.from_name"
            name="formData.from_name">
            <x-slot:feedbackInfo>
                {{ __('admin/config.form.from_name_helper') }}
            </x-slot:feedbackInfo>
        </x-form.text-input>
    </x-form.group>

    <x-ui.buttons.pill size="sm" type="submit" btnText="{{ __('buttons.save_changes') }}"></x-ui.buttons.pill>
</form>
