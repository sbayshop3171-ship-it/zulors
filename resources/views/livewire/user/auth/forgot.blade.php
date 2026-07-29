<div>
    <form method="POST" wire:submit.prevent="submitForm">
        <div class="mb-6">
            <x-auth.parts.form-header title="{{ __('auth.restore_access')}}">
                <x-slot:icon>
                    <x-ui-icon name="lock-unlocked-02" type="line"></x-ui-icon>
                </x-slot:icon>
                <x-slot:caption>
                    {{ __('auth.restore_access_helper', ['app_name' => config('app.name')]) }}
                </x-slot:caption>
            </x-auth.parts.form-header>
        </div>

        <div class="block">
            <div class="mb-3">
                <x-form.text-input
                    name="emailAddress"
                    type="email"
                    wire:model.trim="emailAddress"
                placeholder="{{ __('auth.enter_email') }}"></x-form.text-input>
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
