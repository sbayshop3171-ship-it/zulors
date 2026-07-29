<form wire:submit.prevent="submitForm" enctype="multipart/form-data">
    @csrf
    <x-form.group>
        <x-form.text-input
            labelText="{{ __('admin/config.form.header_code') }}"
            type="text"
            :asText="true"
            placeholder="E.g. <script>console.log('Hello, world!');</script>"
            wire:model="formData.header_code"
            name="formData.header_code">
            <x-slot:feedbackInfo>
                {{ __('admin/config.form.header_code_helper') }}
            </x-slot:feedbackInfo>
        </x-form.text-input>
    </x-form.group>
    <x-form.group>
        <x-form.switcher
            labelText="{{ __('admin/config.form.header_code_enabled') }}"
            wire:model="formData.header_code_enabled"
            name="formData.header_code_enabled">
        </x-form.switcher>
    </x-form.group>
    <div class="mb-6">
        <x-div/>
    </div>
    <x-form.group>
        <x-form.text-input
            labelText="{{ __('admin/config.form.footer_code') }}"
            type="text"
            :asText="true"
            placeholder="E.g. <script>console.log('Hello, world!');</script>"
            wire:model="formData.footer_code"
            name="formData.footer_code">
            <x-slot:feedbackInfo>
                {{ __('admin/config.form.footer_code_helper') }}
            </x-slot:feedbackInfo>
        </x-form.text-input>
    </x-form.group>
    <x-form.group>
        <x-form.switcher
            labelText="{{ __('admin/config.form.footer_code_enabled') }}"
            wire:model="formData.footer_code_enabled"
            name="formData.footer_code_enabled">
        </x-form.switcher>
    </x-form.group>

    <x-ui.buttons.pill size="sm" type="submit" btnText="{{ __('buttons.save_changes') }}"></x-ui.buttons.pill>
</form>


