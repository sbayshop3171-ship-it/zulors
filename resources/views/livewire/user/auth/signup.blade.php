<div>
    <form method="POST" wire:submit.prevent="submitForm">
        <div class="mb-6">
            <x-auth.parts.form-header title="{{ __('auth.signup_for_app.title', ['app_name' => config('app.name')]) }}">
                <x-slot:caption>
                    {{ __('auth.signup_for_app.caption') }}
                </x-slot:caption>
            </x-auth.parts.form-header>
        </div>

        <div class="block">
            @include('livewire.user.auth.parts.social-buttons')

            <div class="mb-3">
                <x-form.text-input
                    name="emailAddress"
                    type="email"
                    wire:model.trim="emailAddress"
                placeholder="{{ __('auth.enter_email') }}"></x-form.text-input>
            </div>
            <div class="mb-6">
                <x-ui.buttons.pill width="w-full" wire:loading.attr="disabled" type="submit" btnText="{{ __('auth.email_continue') }}"></x-ui.buttons.pill>
                <a href="{{ route('user.auth.index') }}" class="block">
                    <x-ui.buttons.pill width="w-full" variant="link" btnText="{{ __('auth.already_have_account') }}"></x-ui.buttons.pill>
                </a>
            </div>

            @include('livewire.user.auth.parts.agreement')
        </div>
    </form>
</div>
