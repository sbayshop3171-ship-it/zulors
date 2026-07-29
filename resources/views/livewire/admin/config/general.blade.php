<form wire:submit.prevent="submitForm">
    @csrf

    <x-form.group>
        <x-form.text-input
            labelText="{{ __('admin/config.form.app_name') }}"
            wire:model="formData.name"
        name="formData.name">
        </x-form.text-input>
    </x-form.group>
    <x-form.group>
        <x-form.text-input
            :asText="true"
            labelText="{{ __('admin/config.form.app_description') }}"
            wire:model="formData.description"
        name="formData.description">
        </x-form.text-input>
    </x-form.group>
    <x-form.group>
        <x-form.text-input
            :asText="true"
            labelText="{{ __('admin/config.form.app_keywords') }}"
            wire:model="formData.keywords"
        name="formData.keywords">
            <x-slot:feedbackInfo>
                {{ __('admin/config.form.app_keywords_helper') }}
            </x-slot:feedbackInfo>
        </x-form.text-input>
    </x-form.group>
    <x-form.group>
        <x-form.select
            :options="$timezones"
            placeholder="UTC"
            name="formData.timezone"
            wire:model="formData.timezone"
            labelText="{{ __('admin/config.form.app_timezone') }}">
            <x-slot:feedbackInfo>
                {{ __('admin/config.form.app_timezone_helper') }}
            </x-slot:feedbackInfo>
        </x-form.select>
    </x-form.group>
    <x-form.group>
        <x-form.select
            :options="$locales"
            placeholder="EN"
            name="formData.locale"
            wire:model="formData.locale"
            labelText="{{ __('admin/config.form.app_locale') }}">
            <x-slot:feedbackInfo>
                {{ __('admin/config.form.app_locale_helper') }}
            </x-slot:feedbackInfo>
        </x-form.select>
    </x-form.group>

    @if(me()->isRoot())
        <div class="mb-6">
            <x-div/>
        </div>

        <x-form.group>
            <x-form.switcher
                labelText="{{ __('admin/config.form.hide_sensitive_data') }}"
                wire:model="formData.hide_sensitive_data"
                name="formData.hide_sensitive_data">
            </x-form.switcher>

            <x-form.helper-text>
                {{ __('admin/config.form.hide_sensitive_data_helper') }}
            </x-form.helper-text>
        </x-form.group>
    @endif

    <x-ui.buttons.pill
        type="submit"
        size="sm"
    btnText="{{ __('buttons.save_changes') }}"></x-ui.buttons.pill>
</form>
