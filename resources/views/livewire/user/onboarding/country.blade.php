<div>
    <div class="mb-4 cursor-pointer" x-cloak>
        <x-form.select
            :hasLabel="false"
            :options="$worldCountries"
            wire:model.live="country"
        placeholder="{{ __('labels.whats_country') }}"></x-form.select>

        @error('country')
            <x-form.valerr>
                {{ $message }}
            </x-form.valerr>
        @endif
    </div>

    <div class="mb-8">
        <x-ui.buttons.pill width="w-full" wire:loading.attr="disabled" type="button" btnText="{{ __('labels.continue') }}" wire:click="submitForm"></x-ui.buttons.pill>
        @if($stepData['is_skippable'])
            <x-ui.buttons.pill width="w-full" variant="link" btnText="{{ __('labels.skip_step') }}" wire:click="skipStep"></x-ui.buttons.pill>
        @endif
    </div>
</div>
