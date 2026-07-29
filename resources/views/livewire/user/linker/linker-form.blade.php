<div>
    <form method="POST" wire:submit.prevent="submitForm">
        <div class="mb-6">
            <x-auth.parts.form-header title="{{ __('auth.linker_login.title')}}">
                <x-slot:icon>
                    <x-ui-icon name="user-right-01" type="line"></x-ui-icon>
                </x-slot:icon>
                <x-slot:caption>
                    {{ __('auth.linker_login.caption', ['app_name' => config('app.name')]) }}
                </x-slot:caption>
            </x-auth.parts.form-header>
        </div>

        <div class="block">
            <div class="mb-3">
                @if($loginStep == 2)
                    <x-form.user-card :userData="$userData" />
                @endif
                <x-form.text-input
                    name="authCreds.login"
                    wire:model.trim="authCreds.login"
                placeholder="{{ __('auth.login_or_email') }}"></x-form.text-input>

                @if($loginStep == 2)
                    <div class="mt-3">
                        <x-form.text-input
                            name="authCreds.password"
                            :isPassword="true"
                            wire:model.trim="authCreds.password"
                        placeholder="{{ __('auth.enter_password') }}"></x-form.text-input>
                    </div>
                @endif
            </div>

            <div class="mb-6">
                <x-ui.buttons.pill width="w-full" wire:loading.attr="disabled" type="submit" btnText="{{ __('auth.linker_login.button') }}"></x-ui.buttons.pill>
                <a href="{{ route('user.desktop.index') }}" class="block">
                    <x-ui.buttons.pill width="w-full" variant="link" btnText="{{ __('labels.back_to_home') }}"></x-ui.buttons.pill>
                </a>
            </div>

            @include('livewire.user.auth.parts.agreement')
        </div>
    </form>
</div>
