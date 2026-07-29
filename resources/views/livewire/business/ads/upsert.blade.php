<div>
    <form wire:submit.prevent="submitForm">
        @csrf
        <x-accordion.form title="{{ __('business/ads.form.base_info') }}">

            <div class="mb-6">
                <x-form.text-input
                    wire:model="formData.title"
                    name="formData.title"
                    labelText="{{ __('business/ads.form.title') }} *"
                placeholder="{{ __('business/ads.form.title_placeholder') }}">

                    <x-slot:feedbackInfo>
                        {{ __('business/jobs.form.title_helper') }}
                    </x-slot:feedbackInfo>
                </x-form.text-input>
            </div>
            <div class="mb-6">
                <x-form.text-input
                    labelText="{{ __('business/ads.form.content') }} *"
                    :asText="true"
                    wire:model="formData.content"
                    name="formData.content"
                    placeholder="{{ __('business/ads.form.content_placeholder') }}">

                    <x-slot:feedbackInfo>
                        {{ __('business/ads.form.content_helper') }}
                    </x-slot:feedbackInfo>
                </x-form.text-input>
            </div>
            <div class="mb-10">
                <x-form.text-input
                    labelText="{{ __('business/ads.form.cta') }} *"
                    wire:model="formData.cta_text"
                    name="formData.cta_text"
                    placeholder="{{ __('business/ads.form.cta_placeholder') }}">

                    <x-slot:feedbackInfo>
                        {{ __('business/ads.form.cta_helper') }}
                    </x-slot:feedbackInfo>
                </x-form.text-input>
            </div>
        </x-accordion.form>
        <x-accordion.form title="{{ __('business/ads.form.media_info') }}">
            <label class="mb-1 font-normal block text-lab-pr3 text-par-s">
                {{ __('business/ads.form.creative') }} *
            </label>
            <div class="mb-2">
                <div class="grid grid-cols-4 gap-2 business-media-grid">
                    @if($adMedia->isEmpty())
                        <div class="aspect-square" x-data>
                            <button x-on:click="$refs.input.click()" type="button" class="border-2 border-fill-pr border-dashed size-full rounded-md px-4 text-brand-900 transition-all ease-linear hover:border-brand-900">
                                <span class="flex flex-col items-center justify-center size-full">
                                    <span class="size-6 mb-2">
                                        <x-ui-icon name="plus" type="solid"></x-ui-icon>
                                    </span>
                                    <span class="text-cap-s text-center leading-none ml-1 font-medium">
                                        {{ __('business/ads.form.creative_placeholder') }}
                                    </span>
                                </span>
                            </button>
                            <input x-ref="input" wire:model="creative" type="file" class="hidden" accept="image/*">
                        </div>
                    @endif
                    @if($adMedia->isNotEmpty())
                        @foreach($adMedia as $mediaItem)
                            <div class="relative aspect-square border border-bord-pr rounded-md overflow-hidden cursor-pointer smoothing group">
                                <div class="absolute invisible inset-0 flex-center bg-black/40 z-10 group-hover:visible smoothing">
                                    <x-trash-btn wire:click="deleteAdCreative({{ $mediaItem->id }})"></x-trash-btn>
                                </div>
                                <img class="size-full object-cover" src="{{ $mediaItem->source_url }}" alt="Image">
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>
            <div class="mb-6">
                <p class="text-cap-l text-lab-sc px-1">
                    {!! __('business/ads.form.creative_helper', ['width' => config('ads.ad.image_width'), 'height' => config('ads.ad.image_height')]) !!}
                </p>

                @error('creative')
                    <x-form.valerr>
                        {{ $message }}
                    </x-form.valerr>
                @enderror
            </div>
        </x-accordion.form>
        <x-accordion.form title="{{ __('business/ads.form.budget_targeting') }}">
            <div class="mb-10">
                <x-form.text-input
                    labelText="{{ __('business/ads.form.budget') }} *"
                    inputType="number"
                    step="0.01"
                    min="{{ config('ads.ad.validation.total_budget.min') }}"
                    max="{{ config('ads.ad.validation.total_budget.max') }}"
                    wire:model="formData.total_budget"
                    :isReadonly="$upsertType == 'edit'"
                    name="formData.total_budget"
                    placeholder="{{ __('business/ads.form.budget_placeholder') }}">
                    <x-slot:feedbackInfo>
                        {{ __('business/ads.form.budget_helper') }}
                    </x-slot:feedbackInfo>
                </x-form.text-input>
            </div>
            <div class="mb-10">
                <x-form.text-input
                    labelText="{{ __('business/ads.form.price_per_view') }} *"
                    inputType="number"
                    step="0.01"
                    min="{{ config('ads.price_per_view_limits.min') }}"
                    max="{{ config('ads.price_per_view_limits.max') }}"
                    wire:model="formData.price_per_view"
                    name="formData.price_per_view"
                    placeholder="{{ __('business/ads.form.price_per_view_placeholder') }}">
                    <x-slot:feedbackInfo>
                        {{ __('business/ads.form.price_per_view_helper', [
                            'min' => config('ads.price_per_view_limits.min'),
                            'max' => config('ads.price_per_view_limits.max')
                        ]) }}
                    </x-slot:feedbackInfo>
                </x-form.text-input>
            </div>
            <div class="mb-10">
                <x-form.text-input
                    labelText="{{ __('business/ads.form.target_topics') }}"
                    wire:model="formData.target_topics"
                    name="formData.target_topics"
                    placeholder="{{ __('business/ads.form.target_topics_placeholder') }}">
                    <x-slot:feedbackInfo>
                        {{ __('business/ads.form.target_topics_helper', ['limit' => config('ads.targeting.topics_limit')]) }}
                    </x-slot:feedbackInfo>
                </x-form.text-input>
            </div>
            <div class="mb-10">
                <x-form.text-input
                    labelText="{{ __('business/ads.form.target_url') }} *"
                    inputType="url"
                    wire:model="formData.target_url"
                    name="formData.target_url"
                    placeholder="{{ __('business/ads.form.target_url_placeholder') }}">

                    <x-slot:feedbackInfo>
                        {{ __('business/ads.form.target_url_helper') }}
                    </x-slot:feedbackInfo>
                </x-form.text-input>
            </div>
            <div class="block">
                <div class="business-form-actions mb-6">
                    <x-ui.buttons.pill size="sm" wire:loading.attr="disabled" type="submit" btnText="{{ route_is('business.ads.create') ? __('business/ads.form.create_button') : __('business/ads.form.save_button') }}"></x-ui.buttons.pill>

                    <a href="{{ route('business.ads.index') }}">
                        <x-ui.buttons.pill
                            variant="danger"
                            size="sm"
                        btnText="{{ __('business/ads.form.cancel_button') }}"></x-ui.buttons.pill>
                    </a>
                </div>
                <div class="text-cap-l text-lab-sc">
                    <p class="mb-4">
                        {!! __('business/ads.form.tos_agreement') !!}
                    </p>
                    <div class="block">
                        <a target="_blank" href="{{ asset(config('ads.document_links.advertising_guide')) }}" class="text-brand-900 underline">
                            {{ __('business/ads.form.tos_agreement_link') }}
                        </a>
                    </div>
                </div>
            </div>
        </x-accordion.form>
    </form>
</div>
