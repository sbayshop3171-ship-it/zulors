<div>
    <div class="mb-6">
        <x-form.text-input
            name="username"
            wire:model="username"
        placeholder="{{ __('labels.choose_username') }}"></x-form.text-input>

        @if($isAvailable)
            <x-form.helper-text>
                {{ __('validation.username_available') }} &check;
            </x-form.helper-text>
        @elseif(empty($username) != true && ($username != me()->username))
            <x-form.helper-text>
                {{ __('validation.username_unavailable') }}
            </x-form.helper-text>
        @endif

        <x-form.helper-text>
            {{ __('labels.choose_username_helper')}}
        </x-form.helper-text>
    </div>

    @if(config('user.require.password'))
        <div class="mb-6">
            <x-form.text-input
                name="password"
                wire:model="password"
                type="password"
                :isPassword="true"
            placeholder="{{ __('labels.password') }}"></x-form.text-input>
        </div>
    @endif

    <div class="mb-4">
        <x-ui.buttons.pill width="w-full" wire:loading.attr="disabled" type="button" btnText="{{ __('labels.continue') }}" wire:click="submitForm"></x-ui.buttons.pill>
    </div>
</div>
