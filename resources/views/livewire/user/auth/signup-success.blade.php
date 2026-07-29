<div>
    <form method="POST" wire:submit.prevent="submitForm">
        <div class="mb-16">
            <div class="mb-4">
                <x-auth.parts.form-header title="{{ __('auth.signup_success_message.title') }}">
                    <x-slot:icon>
                        <x-ui-icon name="mail-01" type="line"></x-ui-icon>
                    </x-slot:icon>
                    <x-slot:caption>
                        {{ __('auth.signup_success_message.caption', ['email_address' => $confirmationData->email]) }}
                    </x-slot:caption>
                </x-auth.parts.form-header>
            </div>

            <x-div></x-div>
        </div>

        <div class="block">
            @if($emailResent)
                <div class="mb-2">
                    <x-form.helper-text type="success">
                        {{ __('auth.resend_link_success') }} &check;
                    </x-form.helper-text>
                </div>
            @else
                <div class="mb-2">
                    <x-form.helper-text>
                        {{ __('auth.resend_link_helper') }}
                    </x-form.helper-text>
                </div>
            @endif
            <div class="mb-6">
                <x-ui.buttons.pill width="w-full" wire:loading.attr="disabled" type="submit" btnText="{{ __('auth.resend_link') }}"></x-ui.buttons.pill>

                @error('resend-timeout')
                    <x-form.valerr>
                        {{ $message }}
                    </x-form.valerr>
                @enderror
                <a href="{{ route('user.auth.signup') }}" class="block">
                    <x-ui.buttons.pill width="w-full" variant="link" btnText="{{ __('auth.already_have_account') }}"></x-ui.buttons.pill>
                </a>
            </div>

            @include('livewire.user.auth.parts.agreement')
        </div>
    </form>
</div>
