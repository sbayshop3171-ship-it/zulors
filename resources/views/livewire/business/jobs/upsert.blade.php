<div>
    <form wire:submit.prevent="submitForm">
        @csrf
        <x-accordion.form title="{{ __('business/jobs.form.base_info') }}">
            <div class="mb-6">
                <x-form.text-input
                    wire:model="formData.title"
                    name="formData.title"
                    labelText="{{ __('business/jobs.form.title') }} *"
                placeholder="{{ __('business/jobs.form.title_placeholder') }}">

                    <x-slot:feedbackInfo>
                        {{ __('business/jobs.form.title_helper') }}
                    </x-slot:feedbackInfo>
                </x-form.text-input>
            </div>
            <div class="mb-6">
                <x-form.text-input
                    labelText="{{ __('business/jobs.form.overview') }} *"
                    :asText="true"
                    wire:model="formData.overview"
                    name="formData.overview"
                placeholder="{{ __('business/jobs.form.overview_placeholder') }}">

                    <x-slot:feedbackInfo>
                        {{ __('business/jobs.form.overview_helper') }}
                    </x-slot:feedbackInfo>
                </x-form.text-input>
            </div>
            <div class="mb-6">
                <x-form.text-input
                    labelText="{{ __('business/jobs.form.desc') }} *"
                    :asText="true"
                    wire:model="formData.description"
                    name="formData.description"
                placeholder="{{ __('business/jobs.form.desc_placeholder') }}">

                    <x-slot:feedbackInfo>
                        {{ __('business/jobs.form.desc_helper') }}
                    </x-slot:feedbackInfo>
                </x-form.text-input>
            </div>
            <div class="mb-6">
                <x-form.select
                        :options="$categories"
                        placeholder="{{ __('business/jobs.form.category_placeholder') }}"
                        name="formData.category_id"
                        wire:model="formData.category_id"
                    labelText="{{ __('business/jobs.form.category') }} *">
                </x-form.select>
            </div>
            <div class="mb-6">
                <x-form.radio-group labelText="{{ __('business/jobs.form.job_type') }} *">
                    <x-form.radio
                            name="formData.type"
                            wire:model="formData.type"
                            defaultValue="vacancy"
                            :checked="true"
                        labelText="{{ __('business/labels.type_labels.vacancy') }}">
                    </x-form.radio>
                    <x-form.radio
                            name="formData.type"
                            defaultValue="project"
                            wire:model="formData.type"
                        labelText="{{ __('business/labels.type_labels.project') }}">
                    </x-form.radio>
                </x-form.radio-group>
            </div>
        </x-accordion.form>
        <x-accordion.form title="{{ __('business/jobs.form.income') }}">
            <div class="mb-6">
                <x-form.text-input
                    labelText="{{ __('business/jobs.form.income_amount') }}"
                    wire:model="formData.income"
                    name="formData.income"
                placeholder="{{ __('business/jobs.form.income_placeholder') }}">

                    <x-slot:feedbackInfo>
                        {{ __('business/jobs.form.income_helper') }}
                    </x-slot:feedbackInfo>
                </x-form.text-input>
            </div>
            <div class="mb-10">
                <x-form.radio-group labelText="{{ __('business/jobs.form.income_range') }}">
                    <x-form.radio
                        name="formData.is_start_income"
                        wire:model="formData.is_start_income"
                        defaultValue="yes"
                    labelText="{{ __('business/jobs.form.income_range_start') }}">
                    </x-form.radio>
                    <x-form.radio
                        name="formData.is_start_income"
                        wire:model="formData.is_start_income"
                        defaultValue="no"
                    labelText="{{ __('business/jobs.form.income_range_end') }}">
                    </x-form.radio>
                </x-form.radio-group>
            </div>
            <div class="mb-6">
                <x-form.select
                        :options="$currencies"
                        name="formData.currency"
                        wire:model="formData.currency"
                        placeholder="{{ __('business/jobs.form.currency_placeholder') }}"
                    labelText="{{ __('business/jobs.form.currency') }} *">

                    <x-slot:feedbackInfo>
                        {{ __('business/jobs.form.currency_helper') }}
                    </x-slot:feedbackInfo>
                </x-form.select>
            </div>
        </x-accordion.form>
        <x-accordion.form title="{{ __('business/jobs.form.additional') }}">
            <div class="mb-10">
                <x-form.radio-group labelText="{{ __('business/jobs.form.urgency') }}">
                    <x-form.radio
                            name="formData.is_urgent"
                            wire:model="formData.is_urgent"
                            defaultValue="yes"
                        labelText="{{ __('business/jobs.form.urgent') }}">
                    </x-form.radio>
                    <x-form.radio
                            name="formData.is_urgent"
                            wire:model="formData.is_urgent"
                            defaultValue="no"
                        labelText="{{ __('business/jobs.form.not_urgent') }}">
                    </x-form.radio>
                </x-form.radio-group>
            </div>
            <div class="mb-10">
                <x-form.text-input
                    labelText="{{ __('business/jobs.form.address') }}"
                    wire:model="formData.location"
                    name="formData.location"
                placeholder="{{ __('business/jobs.form.address_placeholder') }}">

                    <x-slot:feedbackInfo>
                        {{ __('business/jobs.form.address_helper') }}
                    </x-slot:feedbackInfo>
                </x-form.text-input>
            </div>
            <div class="block">
                <div class="business-form-actions mb-6">
                    @if($this->upsertType == 'create')
                        <x-ui.buttons.pill
                            type="submit"
                            size="sm"
                        btnText="{{ __('business/jobs.form.submit') }}"></x-ui.buttons.pill>

                        <a href="{{ route('business.jobs.index') }}">
                            <x-ui.buttons.pill
                                variant="danger"
                                size="sm"
                                type="button"
                            btnText="{{ __('business/jobs.form.cancel') }}"></x-ui.buttons.pill>
                        </a>
                    @else
                        <x-ui.buttons.pill
                            type="submit"
                            size="sm"
                        btnText="{{ __('business/jobs.form.save') }}"></x-ui.buttons.pill>

                        <a href="{{ route('business.jobs.show', $this->jobData->id) }}">
                            <x-ui.buttons.pill
                                variant="danger"
                                type="button"
                                size="sm"
                            btnText="{{ __('business/jobs.form.cancel') }}"></x-ui.buttons.pill>
                        </a>
                    @endif
                </div>
                <p class="text-cap-l text-lab-sc">
                    {!! __('business/jobs.form.tos_agreement') !!}
                    <br>
                    <br>
                    <a href="{{ asset(config('jobs.document_links.posting_rules')) }}" target="_blank" class="text-brand-900 underline">
                        {{ __('business/jobs.form.tos_agreement_link') }}
                    </a>
                </p>
            </div>
        </x-accordion.form>
    </form>
</div>
