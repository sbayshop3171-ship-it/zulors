<form wire:submit.prevent="submitForm">
    @csrf

    <x-form.group>
        <x-form.text-input
            labelText="{{ __('business/wallet.form.amount') }}"
            wire:model="formData.amount"
            type="number"
            step="0.01"
            name="formData.amount"
            placeholder="{{ __('business/wallet.form.amount_placeholder') }}">
            <x-slot:feedbackInfo>
                {{ __('business/wallet.form.amount_helper') }}
            </x-slot:feedbackInfo>
        </x-form.text-input>
    </x-form.group>
    <x-form.group>
        <x-form.select
            :options="$cashoutMethods"
            placeholder="{{ __('business/wallet.form.payment_method_placeholder') }}"
            labelText="{{ __('business/wallet.form.payment_method') }}"
            wire:model="formData.payment_method"
            name="formData.payment_method">
        </x-form.select>
    </x-form.group>
    <x-form.group>
        <x-form.text-input
            labelText="{{ __('business/wallet.form.credentials') }}"
            wire:model="formData.credentials"
            placeholder="{{ __('business/wallet.form.credentials_placeholder') }}"
            :asText="true"
            name="formData.credentials">
            <x-slot:feedbackInfo>
                {{ __('business/wallet.form.credentials_helper') }}
            </x-slot:feedbackInfo>
        </x-form.text-input>
    </x-form.group>

    <x-div classes="mb-6"/>

    <x-form.group>
        <x-form.text-input
            labelText="{{ __('business/wallet.form.reviewer_notes') }}"
            wire:model="formData.reviewer_notes"
            :asText="true"
            placeholder="{{ __('business/wallet.form.reviewer_notes_placeholder') }}"
            name="formData.reviewer_notes">
            <x-slot:feedbackInfo>
                {{ __('business/wallet.form.reviewer_notes_helper') }}
            </x-slot:feedbackInfo>
        </x-form.text-input>
    </x-form.group>

    <div class="business-form-actions business-form-actions-single">
        <x-ui.buttons.pill size="sm" type="submit" btnText="{{ __('buttons.submit_request') }}"></x-ui.buttons.pill>
    </div>
</form>
