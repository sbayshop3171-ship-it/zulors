<div>
    <form wire:submit.prevent="submitForm" enctype="multipart/form-data">
        @csrf
        <x-accordion.form title="{{ __('business/market.form.base_info') }}">
            <div class="mb-6">
                <x-form.text-input
                    labelText="{{ __('business/market.form.name') }} *"
                    wire:model="formData.title"
                    name="formData.title"
                    placeholder="{{ __('business/market.form.name_placeholder') }}">

                    <x-slot:feedbackInfo>
                        {{ __('business/market.form.name_helper') }}
                    </x-slot:feedbackInfo>
                </x-form.text-input>
            </div>
            <div class="mb-6">
                <x-form.text-input
                    labelText="{{ __('business/market.form.desc') }} *"
                    :asText="true"
                    wire:model="formData.description"
                    name="formData.description"
                    placeholder="{{ __('business/market.form.name_placeholder') }}">

                    <x-slot:feedbackInfo>
                        {{ __('business/market.form.desc_helper') }}
                    </x-slot:feedbackInfo>
                </x-form.text-input>
            </div>
            <div class="mb-6">
                <x-form.select
                    :options="$categories"
                    name="formData.category_id"
                    wire:model="formData.category_id"
                    placeholder="{{ __('business/market.form.category_placeholder') }}"
                labelText="{{ __('business/market.form.category') }} *"></x-form.select>
            </div>
            <div class="mb-6">
                <x-form.radio-group labelText="{{ __('business/market.form.type') }}">
                    <x-form.radio
                            name="formData.type"
                            defaultValue="physical"
                            wire:model.live="formData.type"
                        labelText="{{ __('business/market.form.type_physical') }}">
                    </x-form.radio>
                    <x-form.radio
                            name="formData.type"
                            defaultValue="digital"
                            wire:model.live="formData.type"
                        labelText="{{ __('business/market.form.type_digital') }}">
                    </x-form.radio>
                </x-form.radio-group>

                <div class="block mt-1">
                    <p class="text-cap-l text-lab-sc">
                        {!! __('business/market.form.type_helper') !!}
                    </p>
                </div>
            </div>
            <div class="mb-6">
                <x-form.select
                    :options="$conditions"
                    name="formData.condition"
                    wire:model="formData.condition"
                    placeholder="{{ __('business/market.form.condition_placeholder') }}"
                labelText="{{ __('business/market.form.condition') }} *"></x-form.select>
            </div>
            <div class="mb-6">
                <x-form.text-input
                    labelText="{{ __('business/market.form.stock_qty') }} *"
                    wire:model="formData.stock_quantity"
                    name="formData.stock_quantity"
                    type="number"
                    placeholder="{{ __('business/market.form.stock_qty_placeholder') }}">

                    <x-slot:feedbackInfo>
                        {{ __('business/market.form.stock_qty_helper') }}
                    </x-slot:feedbackInfo>
                </x-form.text-input>
            </div>
        </x-accordion.form>
        <x-accordion.form title="{{ __('business/market.form.price_info') }}">
            <div class="mb-6">
                <x-form.text-input
                    labelText="{{ __('business/market.form.price') }} *"
                    wire:model="formData.price"
                    name="formData.price"
                    placeholder="{{ __('business/market.form.price_placeholder') }}">
                </x-form.text-input>
            </div>
            <div class="mb-6">
                <x-form.select
                        :options="$currencies"
                        wire:model="formData.currency"
                        name="formData.currency"
                    labelText="{{ __('business/market.form.currency') }} *">
                </x-form.select>
            </div>
            <div class="mb-6">
                <x-form.radio-group labelText="{{ __('business/market.form.discount') }}">
                    <x-form.radio
                            name="formData.with_discount"
                            wire:model.live="formData.with_discount"
                            defaultValue="no"
                        labelText="{{ __('business/market.form.discount_off') }}">
                    </x-form.radio>
                    <x-form.radio
                            name="formData.with_discount"
                            wire:model.live="formData.with_discount"
                            defaultValue="yes"
                        labelText="{{ __('business/market.form.discount_on') }}">
                    </x-form.radio>
                </x-form.radio-group>
            </div>
            @if($formData['with_discount'] == 'yes')
                <div class="mb-6">
                    <x-form.text-input
                        labelText="{{ __('business/market.form.discount_rate') }} *"
                        wire:model="formData.discount"
                        name="formData.discount"
                        placeholder="{{ __('business/market.form.discount_rate_placeholder') }}">
                        <x-slot:feedbackInfo>
                            {{ __('business/market.form.discount_rate_helper') }}
                        </x-slot:feedbackInfo>
                    </x-form.text-input>
                </div>
            @endif
        </x-accordion.form>
        <x-accordion.form title="{{ __('business/market.form.media_info') }}">
            <label class="mb-1 font-normal block text-lab-pr3 text-par-s">
                {{ __('business/market.form.photos') }} *
            </label>
            <div class="mb-2">
                <div class="grid grid-cols-4 gap-2 business-media-grid">
                    <div class="aspect-square" x-data>
                        <button x-on:click="$refs.input.click()" type="button" class="border-2 cursor-pointer border-fill-pr border-dashed size-full rounded-md px-4 text-brand-900 transition-all ease-linear hover:border-brand-900">
                            <span class="flex flex-col items-center justify-center size-full">
                                <span class="size-6 mb-2">
                                    <x-ui-icon name="plus" type="solid"></x-ui-icon>
                                </span>
                                <span class="text-cap-s text-center leading-none ml-1 font-medium">
                                    {{ __('business/market.form.photos_placeholder') }}
                                </span>
                            </span>
                        </button>
                        <input x-ref="input" wire:model="image" type="file" class="hidden" accept="image/*">
                    </div>

                    @if($productMedia->isNotEmpty())
                        @foreach($productMedia as $mediaItem)
                            <div class="relative aspect-square border border-bord-pr rounded-md overflow-hidden cursor-pointer smoothing group">
                                <div class="absolute invisible inset-0 flex-center bg-black/40 z-10 group-hover:visible smoothing">
                                    <x-trash-btn wire:click="deleteProductImage({{ $mediaItem->id }})"></x-trash-btn>
                                </div>
                                <img class="size-full object-cover" src="{{ $mediaItem->source_url }}" alt="Image">
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>
            <div class="mb-6">
                <p class="text-cap-l text-lab-sc">
                    {!! __('business/market.form.photos_helper', ['width' => config('marketplace.product.image_width'), 'height' => config('marketplace.product.image_height')]) !!}
                </p>

                @error('image')
                    <x-form.valerr>
                        {{ $message }}
                    </x-form.valerr>
                @enderror
            </div>
        </x-accordion.form>
        <x-accordion.form title="{{ __('labels.additional_info') }}">
            <div class="mb-16">
                <x-form.text-input
                    labelText="{{ __('business/market.form.location') }}"
                    wire:model="formData.address"
                    name="formData.address"
                    placeholder="{{ __('business/market.form.location_placeholder') }}">

                    <x-slot:feedbackInfo>
                        {{ __('business/market.form.location_helper') }}
                    </x-slot:feedbackInfo>
                </x-form.text-input>
            </div>
            <div class="block">
                <div class="business-form-actions mb-6">
                    <x-ui.buttons.pill size="sm" wire:loading.attr="disabled" type="submit" btnText="{{ route_is('business.market.create') ? __('business/market.form.create_button') : __('business/market.form.save_button') }}"></x-ui.buttons.pill>

                    <a href="{{ route('business.market.index') }}">
                        <x-ui.buttons.pill
                            variant="danger"
                            size="sm"
                        btnText="{{ __('business/market.form.cancel_button') }}"></x-ui.buttons.pill>
                    </a>
                </div>
                <div class="text-cap-l text-lab-sc">
                    <p class="mb-4">
                        {!! __('business/market.form.tos_agreement') !!}
                    </p>
                    <div class="block">
                        <a target="_blank" href="{{ asset(config('marketplace.document_links.trade_rules')) }}" class="text-brand-900 underline">
                            {{ __('business/market.form.tos_agreement_link') }}
                        </a>
                    </div>
                </div>
            </div>
        </x-accordion.form>
    </form>
</div>
