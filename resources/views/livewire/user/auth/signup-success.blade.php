<div>
    <form method="POST" wire:submit.prevent="verifyOtp" class="auth-form">
        <div class="auth-form__status">
            <x-auth.parts.form-header title="{{ __('auth.signup_success_message.title') }}">
                <x-slot:icon>
                    <x-ui-icon name="mail-01" type="line"></x-ui-icon>
                </x-slot:icon>
                <x-slot:caption>
                    {{ __('auth.signup_success_message.caption', ['email_address' => $confirmationData->email]) }}
                </x-slot:caption>
            </x-auth.parts.form-header>

            <x-div></x-div>
        </div>

        <div class="block">
            <div class="mb-3">
                <x-form.text-input
                    name="otpCode"
                    inputType="text"
                    labelText="{{ __('auth.otp_code') }}"
                    wire:model.trim="otpCode"
                    inputmode="numeric"
                    pattern="[0-9]*"
                    maxlength="4"
                    autocomplete="one-time-code"
                    classes="text-center font-semibold"
                    placeholder="{{ __('auth.otp_code_placeholder') }}"></x-form.text-input>
                <x-form.helper-text>
                    {{ __('auth.otp_code_helper') }}
                </x-form.helper-text>
            </div>

            @if($emailResent)
                <div class="mb-2">
                    <x-form.helper-text type="success">
                        {{ __('auth.resend_otp_success') }} &check;
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
                <x-ui.buttons.pill width="w-full" wire:loading.attr="disabled" type="submit" btnText="{{ __('auth.verify_otp') }}"></x-ui.buttons.pill>
                <x-ui.buttons.pill width="w-full" wire:click="resendOtp" wire:loading.attr="disabled" type="button" variant="link" btnText="{{ __('auth.resend_otp') }}"></x-ui.buttons.pill>

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
