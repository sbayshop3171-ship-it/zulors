<div>
    <form wire:submit.prevent="submitForm" enctype="multipart/form-data">
        @csrf
        <x-accordion.form title="{{ __('business/settings.form.base_info') }}">
            <div class="mb-6">
                <x-form.text-input
                    labelText="{{ __('business/settings.form.name') }} *"
                    wire:model="formData.name"
                    name="formData.name"
                    placeholder="{{ __('business/settings.form.name_placeholder') }}">

                    <x-slot:feedbackInfo>
                        {{ __('business/settings.form.name_helper') }}
                    </x-slot:feedbackInfo>
                </x-form.text-input>
            </div>
            <div class="mb-6">
                <x-form.text-input
                    labelText="{{ __('business/settings.form.description') }}"
                    wire:model="formData.description"
                    name="formData.description"
                    :asText="true"
                    placeholder="{{ __('business/settings.form.description_placeholder') }}">

                    <x-slot:feedbackInfo>
                        {{ __('business/settings.form.description_helper') }}
                    </x-slot:feedbackInfo>
                </x-form.text-input>
            </div>
            <div class="mb-6">
                <x-form.text-input
                    labelText="{{ __('business/settings.form.email') }} *"
                    wire:model="formData.business_email"
                    name="formData.business_email"
                    type="email"
                    placeholder="{{ __('business/settings.form.email_placeholder') }}">

                    <x-slot:feedbackInfo>
                        {{ __('business/settings.form.email_helper') }}
                    </x-slot:feedbackInfo>
                </x-form.text-input>
            </div>
            <div class="mb-6">
                <x-form.text-input
                    labelText="{{ __('business/settings.form.phone') }} *"
                    wire:model="formData.business_phone"
                    name="formData.business_phone"
                    type="tel"
                    placeholder="{{ __('business/settings.form.phone_placeholder') }}">

                    <x-slot:feedbackInfo>
                        {{ __('business/settings.form.phone_helper') }}
                    </x-slot:feedbackInfo>
                </x-form.text-input>
            </div>
        </x-accordion.form>

        <x-accordion.form title="{{ __('business/settings.form.business_address') }}">
            <div class="mb-6">
                <div class="grid grid-cols-2 gap-4">
                    <x-form.select
                        :options="$countries"
                        name="formData.country"
                        wire:model="formData.country"
                        placeholder="{{ __('business/settings.form.country_placeholder') }}"
                    labelText="{{ __('business/settings.form.country') }} *">

                        <x-slot:feedbackInfo>
                            {{ __('business/settings.form.country_helper') }}
                        </x-slot:feedbackInfo>
                    </x-form.select>
                    <x-form.text-input
                        labelText="{{ __('business/settings.form.city') }} *"
                        wire:model="formData.city"
                        name="formData.city"
                        placeholder="{{ __('business/settings.form.city_placeholder') }}">
                    </x-form.text-input>
                </div>
            </div>
            <div class="mb-6">
                <div class="grid grid-cols-2 gap-4">
                    <x-form.text-input
                        labelText="{{ __('business/settings.form.postal_code') }} *"
                        wire:model="formData.postal_code"
                        name="formData.postal_code"
                        placeholder="{{ __('business/settings.form.postal_code_placeholder') }}">
                    </x-form.text-input>
                    <x-form.text-input
                        labelText="{{ __('business/settings.form.state') }}"
                        wire:model="formData.state"
                        name="formData.state"
                        placeholder="{{ __('business/settings.form.state_placeholder') }}">
                    </x-form.text-input>
                </div>
            </div>
            <div class="mb-6">
                <x-form.text-input
                    labelText="{{ __('business/settings.form.billing_address') }} *"
                    wire:model="formData.billing_address"
                    name="formData.billing_address"
                    placeholder="{{ __('business/settings.form.billing_address_placeholder') }}">
                    <x-slot:feedbackInfo>
                        {{ __('business/settings.form.billing_address_helper') }}
                    </x-slot:feedbackInfo>
                </x-form.text-input>
            </div>
            <div class="mb-6">
                <x-form.text-input
                    labelText="{{ __('business/settings.form.address_line1') }} *"
                    wire:model="formData.address_line1"
                    name="formData.address_line1"
                    placeholder="{{ __('business/settings.form.address_line_placeholder') }}">
                </x-form.text-input>
            </div>
            <div class="mb-6">
                <x-form.text-input
                    labelText="{{ __('business/settings.form.address_line2') }} *"
                    wire:model="formData.address_line2"
                    name="formData.address_line2"
                    placeholder="{{ __('business/settings.form.address_line_placeholder') }}">
                    <x-slot:feedbackInfo>
                        {{ __('business/settings.form.address_line2_helper') }}
                    </x-slot:feedbackInfo>
                </x-form.text-input>
            </div>
        </x-accordion.form>

        <x-accordion.form title="{{ __('business/settings.form.tax_info') }}">
            <div class="mb-6">
                <x-form.text-input
                    labelText="{{ __('business/settings.form.tax_number') }} *"
                    wire:model="formData.tax_number"
                    name="formData.tax_number"
                    placeholder="{{ __('business/settings.form.tax_number_placeholder') }}">
                    <x-slot:feedbackInfo>
                        {{ __('business/settings.form.tax_number_helper') }}
                    </x-slot:feedbackInfo>
                </x-form.text-input>
            </div>
        </x-accordion.form>

        <x-accordion.form title="{{ __('labels.additional_info') }}">
            <div class="mb-10">
                <x-form.text-input
                    labelText="{{ __('business/settings.form.website') }}"
                    wire:model="formData.website"
                    name="formData.website"
                    placeholder="{{ __('business/settings.form.website_placeholder') }}">
                    <x-slot:feedbackInfo>
                        {{ __('business/settings.form.website_helper') }}
                    </x-slot:feedbackInfo>
                </x-form.text-input>
            </div>

            <div class="block">
                <div class="flex mb-6 gap-2">
                    <x-ui.buttons.pill wire:loading.attr="disabled" size="sm" type="submit" btnText="{{ __('business/settings.form.save_button') }}"></x-ui.buttons.pill>

                    <a href="{{ route('business.settings.index') }}">
                        <x-ui.buttons.pill
                            variant="danger"
                            size="sm"
                        btnText="{{ __('business/settings.form.cancel_button') }}"></x-ui.buttons.pill>
                    </a>
                </div>
                <div class="text-cap-l text-lab-sc">
                    {!! __('business/settings.form.tos_agreement') !!}
                </div>
            </div>
        </x-accordion.form>
    </form>
</div>
