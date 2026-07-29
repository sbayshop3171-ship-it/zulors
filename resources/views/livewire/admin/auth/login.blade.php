<div>
    <form method="POST" wire:submit.prevent="submitForm">
        <div class="mb-6">
            <x-auth.parts.form-header title="{{ __('auth.login_to_cp.title')}}">
                <x-slot:caption>
                    {{ __('auth.login_to_cp.caption') }}
                </x-slot:caption>
            </x-auth.parts.form-header>
        </div>

        <div class="mb-2">
            <x-form.text-input
                name="authCreds.login"
                wire:model.trim="authCreds.login"
            placeholder="{{ __('auth.login_or_email') }}"></x-form.text-input>
        </div>

        <x-form.group>
            <x-form.text-input
                name="authCreds.password"
                :isPassword="true"
                wire:model.trim="authCreds.password"
            placeholder="{{ __('auth.enter_password') }}"></x-form.text-input>
        </x-form.group>

        <x-ui.buttons.pill width="w-full" wire:loading.attr="disabled" type="submit" btnText="{{ __('buttons.login') }}"></x-ui.buttons.pill>
    </form>
</div>
