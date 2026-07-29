<div>
    <div class="mb-3">
        <x-form.text-input
            name="userData.first_name"
            wire:model.trim="userData.first_name"
        placeholder="{{ __('labels.whats_name') }}"></x-form.text-input>
    </div>

    @if ($microSteps['first_name'])
        <div class="mb-8">
            <x-form.text-input
                name="userData.last_name"
                wire:model.trim="userData.last_name"
            placeholder="{{ __('labels.whats_last_name') }}"></x-form.text-input>
        </div>

        @if ($microSteps['last_name'])
            <div class="mb-6" x-cloak>
                <x-form.label>
                    {{ __('labels.birthdate') }}
                </x-form.label>
                <div class="grid grid-cols-3 gap-2 mb-2">
                    <div class="cursor-pointer">
                        <x-form.select
                            wire:model.live="birthMonth"
                            :hasLabel="false"
                            :options="$birthMonthsList"
                        placeholder="{{ __('labels.month') }}"></x-form.select>
                    </div>
                    <div class="cursor-pointer" wire:key="select-birth-day-{{ $birthMonth }}">
                        <x-form.select
                            wire:model.live="birthDay"
                            :hasLabel="false"
                            :options="$birthDaysList"
                        placeholder="{{ __('labels.day') }}"></x-form.select>
                    </div>
                    <div class="cursor-pointer">
                        <x-form.select
                            wire:model.live="birthYear"
                            :hasLabel="false"
                            :options="$birthYearsList"
                        placeholder="{{ __('labels.year') }}"></x-form.select>
                    </div>
                </div>
                @error('birthdateError')
                    <x-form.valerr>
                        {{ $message }}
                    </x-form.valerr>
                @else
                    <x-form.helper-text>
                        {{ __('labels.birthdate_input_helper')}}
                    </x-form.helper-text>
                @endif
            </div>

            <div class="mb-6">
                <x-form.radio-group labelText="{{ __('labels.gender') }}">
                    <x-form.radio wire:change="saveGender('male')" :checked="me()->gender == 'male'" labelText="{{ __('labels.male') }}" name="userData.gender" defaultValue="male"></x-form.radio>
                    <x-form.radio wire:change="saveGender('female')" :checked="me()->gender == 'female'" labelText="{{ __('labels.female') }}" name="userData.gender" defaultValue="female"></x-form.radio>
                </x-form.radio-group>

                <div class="mt-2">
                    <x-form.helper-text>
                        {{ __('labels.not_public_info') }}
                    </x-form.helper-text>
                </div>
            </div>
        @endif
    @endif

    <div class="mb-4">
        <x-ui.buttons.pill width="w-full" wire:loading.attr="disabled" type="button" btnText="{{ __('labels.continue') }}" wire:click="submitForm"></x-ui.buttons.pill>
    </div>
</div>
