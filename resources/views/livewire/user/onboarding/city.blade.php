<div>
    <div class="mb-3">
        <x-form.text-input
            name="city"
            wire:model="city"
        placeholder="{{ __('labels.whats_city') }}"></x-form.text-input>
    </div>

    <div class="mb-4">
        <x-ui.buttons.pill width="w-full" wire:loading.attr="disabled" type="button" btnText="{{ __('labels.continue') }}" wire:click="submitForm"></x-ui.buttons.pill>
        @if($stepData['is_skippable'])
            <x-ui.buttons.pill width="w-full" variant="link" btnText="{{ __('labels.skip_step') }}" wire:click="skipStep"></x-ui.buttons.pill>
        @endif
    </div>
</div>
