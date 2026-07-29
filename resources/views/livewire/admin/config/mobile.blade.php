<form wire:submit.prevent="submitForm" enctype="multipart/form-data">
    @csrf
    <x-form.group>
        <x-form.text-input
            labelText="{{ __('admin/mobile.form.android_link') }}"
            type="text"
            wire:model="formData.android_link"
            name="formData.android_link">
            <x-slot:feedbackInfo>
                {{ __('admin/mobile.form.android_link_helper') }}
            </x-slot:feedbackInfo>
        </x-form.text-input>
    </x-form.group>

    <x-form.group>
        <x-form.text-input
            labelText="{{ __('admin/mobile.form.ios_link') }}"
            type="text"
            wire:model="formData.ios_link"
            name="formData.ios_link">
            <x-slot:feedbackInfo>
                {{ __('admin/mobile.form.ios_link_helper') }}
            </x-slot:feedbackInfo>
        </x-form.text-input>
    </x-form.group>

    <x-ui.buttons.pill size="sm" type="submit" btnText="{{ __('buttons.save_changes') }}"></x-ui.buttons.pill>
</form>
