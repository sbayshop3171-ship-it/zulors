<form wire:submit.prevent="submitForm">
    @csrf

    <x-form.group>
        <x-callout.default
            iconName="user-plus-01"
            titleText="{{ __('admin/config.callout.registration_enabled.title') }}"
        captionText="{{ __('admin/config.callout.registration_enabled.caption') }}" />

        <x-form.switcher
            labelText="{{ __('admin/config.form.switch_status') }}"
            wire:model="formData.registration_enabled"
            name="formData.registration_enabled">
        </x-form.switcher>
    </x-form.group>
    <div class="mb-6">
        <x-div/>
    </div>
    <x-form.group>
        <x-callout.default
            iconName="login-01"
            titleText="{{ __('admin/config.callout.login_enabled.title') }}"
        captionText="{{ __('admin/config.callout.login_enabled.caption') }}" />
        <x-form.switcher
            labelText="{{ __('admin/config.form.switch_status') }}"
            wire:model="formData.login_enabled"
            name="formData.login_enabled">
        </x-form.switcher>
    </x-form.group>
    <div class="mb-6">
        <x-div/>
    </div>
    <x-form.group>
        <x-callout.default
            iconName="user-check-01"
            titleText="{{ __('admin/config.callout.reg_verification_enabled.title') }}"
        captionText="{{ __('admin/config.callout.reg_verification_enabled.caption') }}" />
        <x-form.switcher
            labelText="{{ __('admin/config.form.switch_status') }}"
            wire:model="formData.reg_verification_enabled"
            name="formData.reg_verification_enabled">
        </x-form.switcher>
    </x-form.group>
    <div class="mb-6">
        <x-div/>
    </div>
    <x-form.group>
        <x-callout.default
            iconName="mask-01"
            titleText="{{ __('admin/config.callout.switch_account_enabled.title') }}"
        captionText="{{ __('admin/config.callout.switch_account_enabled.caption') }}" />
        <x-form.switcher
            labelText="{{ __('admin/config.form.switch_status') }}"
            wire:model="formData.switch_account_enabled"
            name="formData.switch_account_enabled">
        </x-form.switcher>
    </x-form.group>
    <div class="mb-6">
        <x-div/>
    </div>
    <x-form.group>
        <x-callout.default
            iconName="users-01"
            titleText="{{ __('admin/config.callout.link_accounts_enabled.title') }}"
        captionText="{{ __('admin/config.callout.link_accounts_enabled.caption') }}" />
        <x-form.switcher
            labelText="{{ __('admin/config.form.switch_status') }}"
            wire:model="formData.link_accounts_enabled"
            name="formData.link_accounts_enabled">
        </x-form.switcher>
    </x-form.group>
    <x-ui.buttons.pill size="sm" type="submit" btnText="{{ __('buttons.save_changes') }}"></x-ui.buttons.pill>
</form>
