<div>
    <form method="POST" wire:submit.prevent="submitForm">
        <div class="mb-6">
            <x-auth.parts.form-header
                title="{{ __('auth.new_password')}}">

                <x-slot:caption>
                    {{ __('auth.new_password_helper') }}
                </x-slot:caption>
            </x-auth.parts.form-header>
        </div>

        <div class="block">
            <x-form.user-card :userData="$userData" />

            <div class="mb-3">
                <div class="mt-3">
                    <x-form.text-input
                        name="newPassword"
                        :isPassword="true"
                        wire:model.trim="newPassword"
                    placeholder="{{ __('auth.enter_new_password') }}"></x-form.text-input>
                </div>
                <x-form.helper-text>
                    {{ __('auth.password_strength_helper', ['min_length' => config('user.password_min')]) }}
                </x-form.helper-text>
            </div>
            <div class="mb-6">
                <x-ui.buttons.pill width="w-full" wire:loading.attr="disabled" type="submit" btnText="{{ __('labels.continue') }}"></x-ui.buttons.pill>
                <a href="{{ route('user.auth.index') }}" class="block">
                    <x-ui.buttons.pill width="w-full" variant="link" btnText="{{ __('auth.back_to_login') }}"></x-ui.buttons.pill>
                </a>
            </div>

            @include('livewire.user.auth.parts.agreement')
        </div>
    </form>
</div>
