<div class="business-ad-builder" x-data="{ previewOpen: false }">
    <div class="grid grid-cols-[minmax(0,1fr)_minmax(320px,380px)] items-start gap-5 lg:grid-cols-1">
        <aside class="order-2 lg:order-1">
            <div class="sticky top-6 lg:static">
                <div class="mb-3 flex items-start justify-between gap-3">
                    <div>
                    <h3 class="text-par-l font-bold text-lab-pr2">{{ __('business/ads.preview.title') }}</h3>
                    <p class="text-par-s text-lab-sc">{{ __('business/ads.preview.caption') }}</p>
                    </div>
                    <button type="button" x-on:click="previewOpen = true" class="shrink-0 rounded-lg bg-fill-qt px-3 py-2 text-cap-l font-bold text-lab-pr2 transition-opacity hover:opacity-80">
                        See all previews
                    </button>
                </div>

                <div class="overflow-hidden rounded-xl border border-bord-pr bg-bg-pr shadow-xs">
                    <div class="p-3">
                        <div class="mb-3 flex items-center gap-3">
                            <img src="{{ me()->avatar_url }}" alt="{{ me()->name }}" class="size-10 rounded-full object-cover">
                            <div class="min-w-0">
                                <strong class="block truncate text-par-s font-bold text-lab-pr2">{{ me()->name }}</strong>
                                <span class="block text-cap-l font-semibold text-lab-sc">{{ __('api/labels.ad') }}</span>
                            </div>
                        </div>

                        <p class="mb-3 whitespace-pre-line text-par-s leading-relaxed text-lab-pr2">{{ $previewData['content'] }}</p>

                        <div class="mb-3 overflow-hidden rounded-xl bg-fill-fv">
                            @if($previewData['media_type'] === 'video' && $previewData['media_url'])
                                <video
                                    class="aspect-video w-full object-cover"
                                    src="{{ $previewData['media_url'] }}"
                                    poster="{{ $previewData['thumbnail_url'] ?: asset(config('ads.ad.default_preview')) }}"
                                    controls
                                    muted
                                    playsinline></video>
                            @elseif(in_array($previewData['media_type'], ['image', 'gif'], true) && $previewData['media_url'])
                                <img class="aspect-video w-full object-cover" src="{{ $previewData['media_url'] }}" alt="{{ $previewData['title'] }}">
                            @else
                                <div class="flex aspect-video items-center justify-center px-6 text-center text-par-s font-semibold text-lab-sc">
                                    {{ __('business/ads.preview.media_placeholder') }}
                                </div>
                            @endif
                        </div>

                        <div class="rounded-xl bg-fill-fv p-3">
                            <h4 class="line-clamp-2 text-par-m font-bold text-lab-pr2">{{ $previewData['title'] }}</h4>
                            @if($previewData['target_url'])
                                <p class="mt-1 truncate text-cap-l font-semibold text-lab-sc">{{ $previewData['target_url'] }}</p>
                            @endif
                            @if($previewData['cta_text'] !== __('business/ads.form.cta_presets.no_button'))
                                <div class="mt-3">
                                    <button type="button" class="h-10 w-full rounded-xl bg-lab-pr2 px-4 text-par-s font-bold text-bg-pr">
                                        {{ $previewData['cta_text'] }}
                                    </button>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </aside>

        <form class="order-1 lg:order-2" wire:submit.prevent="submitForm">
            @csrf

            <x-accordion.form title="{{ __('business/ads.form.source_type') }}">
                <div class="grid grid-cols-2 gap-3 md:grid-cols-1">
                    <button
                        type="button"
                        wire:click="setSourceType('post')"
                        class="rounded-2xl border p-4 text-left smoothing {{ ($formData['source_type'] ?? 'creative') === 'post' ? 'border-brand-900 bg-fill-qt' : 'border-bord-pr bg-bg-pr' }}">
                        <strong class="block text-par-s text-lab-pr2">{{ __('business/ads.form.boost_post') }}</strong>
                        <span class="mt-1 block text-cap-l text-lab-sc">{{ __('business/ads.form.boost_post_helper') }}</span>
                    </button>

                    <button
                        type="button"
                        wire:click="setSourceType('creative')"
                        class="rounded-2xl border p-4 text-left smoothing {{ ($formData['source_type'] ?? 'creative') === 'creative' ? 'border-brand-900 bg-fill-qt' : 'border-bord-pr bg-bg-pr' }}">
                        <strong class="block text-par-s text-lab-pr2">{{ __('business/ads.form.create_new_ad') }}</strong>
                        <span class="mt-1 block text-cap-l text-lab-sc">{{ __('business/ads.form.create_new_ad_helper') }}</span>
                    </button>
                </div>
            </x-accordion.form>

            @if(($formData['source_type'] ?? 'creative') === 'post')
                <x-accordion.form title="{{ __('business/ads.form.source_post') }}">
                    <div class="mb-6">
                        <x-form.text-input
                            labelText="{{ __('business/ads.form.search_posts') }}"
                            wire:model.live.debounce.300ms="boostPostSearch"
                            name="boostPostSearch"
                            placeholder="{{ __('business/ads.form.search_posts_placeholder') }}">
                            <x-slot:feedbackInfo>
                                {{ __('business/ads.form.search_posts_helper') }}
                            </x-slot:feedbackInfo>
                        </x-form.text-input>
                    </div>

                    <div class="mb-6">
                        <x-form.select
                            :options="$boostablePosts"
                            placeholder="{{ __('business/ads.form.source_post_placeholder') }}"
                            name="formData.source_post_id"
                            wire:model.live="formData.source_post_id"
                            labelText="{{ __('business/ads.form.source_post') }} *">
                            <x-slot:feedbackInfo>
                                {{ __('business/ads.form.source_post_helper') }}
                            </x-slot:feedbackInfo>
                        </x-form.select>
                    </div>

                    @if($selectedSourcePost)
                        <div class="rounded-2xl bg-fill-fv p-4">
                            <strong class="block text-par-s text-lab-pr2">{{ $selectedSourcePost->type->label() }}</strong>
                            <p class="mt-1 line-clamp-3 text-par-s text-lab-sc">{{ $selectedSourcePost->content ?: $selectedSourcePost->title }}</p>
                        </div>
                    @endif
                </x-accordion.form>
            @else
                <x-accordion.form title="{{ __('business/ads.form.base_info') }}">
                    <div class="mb-6">
                        <x-form.text-input
                            wire:model.live.debounce.300ms="formData.title"
                            name="formData.title"
                            labelText="{{ __('business/ads.form.title') }} *"
                            placeholder="{{ __('business/ads.form.title_placeholder') }}">
                            <x-slot:feedbackInfo>
                                {{ __('business/ads.form.title_helper') }}
                            </x-slot:feedbackInfo>
                        </x-form.text-input>
                    </div>

                    <div class="mb-6">
                        <x-form.text-input
                            labelText="{{ __('business/ads.form.content') }} *"
                            :asText="true"
                            wire:model.live.debounce.300ms="formData.content"
                            name="formData.content"
                            placeholder="{{ __('business/ads.form.content_placeholder') }}">
                            <x-slot:feedbackInfo>
                                {{ __('business/ads.form.content_helper') }}
                            </x-slot:feedbackInfo>
                        </x-form.text-input>
                    </div>
                </x-accordion.form>

                <x-accordion.form title="{{ __('business/ads.form.media_info') }}">
                    <div class="mb-5 grid grid-cols-2 gap-3">
                        <button
                            type="button"
                            wire:click="setMediaType('image')"
                            class="rounded-xl border p-3 text-center text-par-s font-bold smoothing {{ ($formData['media_type'] ?? 'image') === 'image' ? 'border-brand-900 bg-fill-qt text-brand-900' : 'border-bord-pr text-lab-sc' }}">
                            {{ __('labels.image') }}
                        </button>
                        <button
                            type="button"
                            wire:click="setMediaType('video')"
                            class="rounded-xl border p-3 text-center text-par-s font-bold smoothing {{ ($formData['media_type'] ?? 'image') === 'video' ? 'border-brand-900 bg-fill-qt text-brand-900' : 'border-bord-pr text-lab-sc' }}">
                            {{ __('labels.video') }}
                        </button>
                    </div>

                    <label class="mb-1 block text-par-s font-normal text-lab-pr3">
                        {{ __('business/ads.form.creative') }} *
                    </label>
                    <div class="mb-2">
                        <div class="grid grid-cols-4 gap-2 business-media-grid">
                            @if($adMedia->isEmpty())
                                <div class="aspect-square" x-data>
                                    <button x-on:click="$refs.input.click()" type="button" class="size-full rounded-md border-2 border-dashed border-fill-pr px-4 text-brand-900 transition-all ease-linear hover:border-brand-900">
                                        <span class="flex size-full flex-col items-center justify-center">
                                            <span class="mb-2 size-6">
                                                <x-ui-icon name="plus" type="solid"></x-ui-icon>
                                            </span>
                                            <span class="ml-1 text-center text-cap-s font-medium leading-none">
                                                {{ ($formData['media_type'] ?? 'image') === 'video' ? __('business/ads.form.upload_video') : __('business/ads.form.upload_image') }}
                                            </span>
                                        </span>
                                    </button>
                                    <input x-ref="input" wire:model="creative" type="file" class="hidden" accept="{{ ($formData['media_type'] ?? 'image') === 'video' ? 'video/mp4,video/webm,video/quicktime' : 'image/*' }}">
                                </div>
                            @endif

                            @if($adMedia->isNotEmpty())
                                @foreach($adMedia as $mediaItem)
                                    <div class="group relative aspect-square cursor-pointer overflow-hidden rounded-md border border-bord-pr">
                                        <div class="invisible absolute inset-0 z-10 flex-center bg-black/40 smoothing group-hover:visible">
                                            <x-trash-btn wire:click="deleteAdCreative({{ $mediaItem->id }})"></x-trash-btn>
                                        </div>
                                        @if($mediaItem->type->isVideo())
                                            <img class="size-full object-cover" src="{{ $mediaItem->thumbnail_url ?: asset(config('ads.ad.default_preview')) }}" alt="Video">
                                        @else
                                            <img class="size-full object-cover" src="{{ $mediaItem->source_url }}" alt="Image">
                                        @endif
                                    </div>
                                @endforeach
                            @endif
                        </div>
                    </div>
                    <div class="mb-6">
                        <p class="px-1 text-cap-l text-lab-sc">
                            @if(($formData['media_type'] ?? 'image') === 'video')
                                {!! __('business/ads.form.video_helper', ['seconds' => config('ads.ad.validation.video.max_duration_seconds'), 'size' => (int) (config('ads.ad.validation.video.max') / 1024)]) !!}
                            @else
                                {!! __('business/ads.form.creative_helper', ['width' => config('ads.ad.image_width'), 'height' => config('ads.ad.image_height')]) !!}
                            @endif
                        </p>

                        @error('creative')
                            <x-form.valerr>
                                {{ $message }}
                            </x-form.valerr>
                        @enderror
                    </div>
                </x-accordion.form>
            @endif

            <x-accordion.form title="{{ __('business/ads.form.cta') }}">
                <div
                    class="mb-6"
                    x-data="{
                        open: false,
                        selected: @js($formData['cta_text'] ?: __('business/ads.form.cta_presets.send_message')),
                        selectOption(value) {
                            this.selected = value;
                            this.open = false;
                            $wire.set('formData.cta_text', value);
                        }
                    }"
                    x-on:keydown.escape.window="open = false"
                    x-on:click.outside="open = false">
                    <label for="formDataCtaText" class="mb-1 block text-par-s font-normal text-lab-pr3">
                        {{ __('business/ads.form.cta') }} *
                    </label>
                    <button
                        id="formDataCtaText"
                        type="button"
                        x-on:click="open = ! open"
                        x-bind:aria-expanded="open"
                        aria-haspopup="listbox"
                        class="flex h-11 w-full items-center justify-between rounded-xl border border-bord-pr bg-bg-pr px-3 text-left text-par-s text-lab-pr2 outline-hidden transition-colors hover:border-brand-900 focus:border-brand-900">
                        <span x-text="selected"></span>
                        <svg class="size-4 shrink-0 transition-transform" x-bind:class="{ 'rotate-180': open }" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.168l3.71-3.938a.75.75 0 1 1 1.08 1.04l-4.25 4.51a.75.75 0 0 1-1.08 0l-4.25-4.51a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd"></path>
                        </svg>
                    </button>
                    <div
                        x-cloak
                        x-show="open"
                        x-transition.origin.top
                        class="relative z-40 mt-2 w-full overflow-hidden rounded-xl border border-bord-pr bg-bg-pr shadow-xl"
                        role="listbox"
                        aria-label="CTA options">
                        @foreach([
                            __('business/ads.form.cta_presets.no_button'),
                            __('business/ads.form.cta_presets.learn_more'),
                            __('business/ads.form.cta_presets.sign_up'),
                            __('business/ads.form.cta_presets.send_message'),
                            __('business/ads.form.cta_presets.call_now'),
                        ] as $ctaPreset)
                            <button
                                type="button"
                                role="option"
                                x-on:click="selectOption(@js($ctaPreset))"
                                x-bind:aria-selected="selected === @js($ctaPreset)"
                                class="flex w-full items-center justify-between gap-3 px-3 py-2.5 text-left transition-colors hover:bg-fill-fv">
                                <span class="min-w-0">
                                    <strong class="block text-par-s font-semibold text-lab-pr2">{{ $ctaPreset }}</strong>
                                    @if($ctaPreset === __('business/ads.form.cta_presets.send_message'))
                                        <small class="mt-0.5 block text-cap-l leading-snug text-lab-sc">Get messages on Messenger, Instagram and WhatsApp</small>
                                    @endif
                                </span>
                                <span class="flex size-5 shrink-0 items-center justify-center rounded-full border-2 border-lab-sc" x-bind:class="{ 'border-brand-900 bg-brand-900': selected === @js($ctaPreset) }">
                                    <span x-show="selected === @js($ctaPreset)" class="size-2 rounded-full bg-bg-pr"></span>
                                </span>
                            </button>
                        @endforeach
                    </div>
                    <input type="hidden"
                        name="formData.cta_text"
                        wire:model.live.debounce.300ms="formData.cta_text">
                    <p class="mt-1 text-cap-l text-lab-sc">{{ __('business/ads.form.cta_helper') }}</p>

                    @if(($formData['cta_text'] ?? '') !== __('business/ads.form.cta_presets.no_button'))
                        <div class="mt-4">
                            <x-form.text-input
                                labelText="{{ __('business/ads.form.target_url') }} *"
                                inputType="url"
                                wire:model.live.debounce.300ms="formData.target_url"
                                name="formData.target_url"
                                placeholder="{{ __('business/ads.form.target_url_placeholder') }}">
                                <x-slot:feedbackInfo>
                                    {{ __('business/ads.form.target_url_helper') }}
                                </x-slot:feedbackInfo>
                            </x-form.text-input>
                        </div>
                    @endif
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

    <button type="button" x-on:click="previewOpen = true" class="fixed bottom-20 right-4 z-30 rounded-full bg-lab-pr2 px-4 py-3 text-par-s font-bold text-bg-pr shadow-lg lg:hidden">
        View preview
    </button>

    <div
        x-cloak
        x-show="previewOpen"
        x-transition.opacity
        x-on:keydown.escape.window="previewOpen = false"
        class="fixed inset-0 z-[70] flex items-end justify-center bg-black/60 p-3 sm:items-center"
        role="dialog"
        aria-modal="true"
        aria-label="Ad previews">
        <div x-on:click.outside="previewOpen = false" class="max-h-[92vh] w-full max-w-5xl overflow-y-auto rounded-2xl bg-bg-pr p-4 shadow-2xl sm:p-6">
            <div class="mb-4 flex items-center justify-between gap-3">
                <div>
                    <h3 class="text-par-l font-bold text-lab-pr2">See all previews</h3>
                    <p class="text-par-s text-lab-sc">Review how your ad can appear across placements.</p>
                </div>
                <button type="button" x-on:click="previewOpen = false" class="size-10 rounded-full bg-fill-qt text-par-l font-bold text-lab-pr2" aria-label="Close previews">&times;</button>
            </div>
            <div class="grid gap-4 md:grid-cols-3">
                <div class="rounded-xl border border-bord-pr bg-fill-fv p-3">
                    <p class="mb-2 text-cap-l font-bold text-lab-sc">Desktop feed</p>
                    <div class="overflow-hidden rounded-lg bg-bg-pr">
                        @if($previewData['media_type'] === 'video' && $previewData['media_url'])
                            <video class="aspect-video w-full object-cover" src="{{ $previewData['media_url'] }}" poster="{{ $previewData['thumbnail_url'] ?: asset(config('ads.ad.default_preview')) }}" controls muted playsinline></video>
                        @elseif(in_array($previewData['media_type'], ['image', 'gif'], true) && $previewData['media_url'])
                            <img class="aspect-video w-full object-cover" src="{{ $previewData['media_url'] }}" alt="{{ $previewData['title'] }}">
                        @else
                            <div class="flex aspect-video items-center justify-center p-4 text-center text-cap-l text-lab-sc">{{ __('business/ads.preview.media_placeholder') }}</div>
                        @endif
                        <div class="p-3"><h4 class="line-clamp-2 text-par-m font-bold text-lab-pr2">{{ $previewData['title'] }}</h4><p class="mt-1 line-clamp-3 text-cap-l text-lab-sc">{{ $previewData['content'] }}</p></div>
                    </div>
                </div>
                <div class="rounded-xl border border-bord-pr bg-fill-fv p-3">
                    <p class="mb-2 text-cap-l font-bold text-lab-sc">Mobile feed</p>
                    <div class="mx-auto max-w-[230px] overflow-hidden rounded-xl bg-bg-pr">
                        @if($previewData['media_type'] === 'video' && $previewData['media_url'])
                            <video class="aspect-[4/5] w-full object-cover" src="{{ $previewData['media_url'] }}" poster="{{ $previewData['thumbnail_url'] ?: asset(config('ads.ad.default_preview')) }}" controls muted playsinline></video>
                        @elseif($previewData['media_url'])
                            <img loading="lazy" class="aspect-[4/5] w-full object-cover" src="{{ $previewData['media_url'] }}" alt="{{ $previewData['title'] }}">
                        @else
                            <div class="flex aspect-[4/5] items-center justify-center p-4 text-center text-cap-l text-lab-sc">{{ __('business/ads.preview.media_placeholder') }}</div>
                        @endif
                        <div class="p-3"><h4 class="line-clamp-2 text-par-m font-bold text-lab-pr2">{{ $previewData['title'] }}</h4><p class="mt-1 line-clamp-3 text-cap-l text-lab-sc">{{ $previewData['content'] }}</p></div>
                    </div>
                </div>
                <div class="rounded-xl border border-bord-pr bg-fill-fv p-3">
                    <p class="mb-2 text-cap-l font-bold text-lab-sc">Stories</p>
                    <div class="mx-auto max-w-[190px] overflow-hidden rounded-xl bg-bg-pr">
                        @if($previewData['media_type'] === 'video' && $previewData['media_url'])
                            <video class="aspect-[9/16] w-full object-cover" src="{{ $previewData['media_url'] }}" poster="{{ $previewData['thumbnail_url'] ?: asset(config('ads.ad.default_preview')) }}" controls muted playsinline></video>
                        @elseif($previewData['media_url'])
                            <img loading="lazy" class="aspect-[9/16] w-full object-cover" src="{{ $previewData['media_url'] }}" alt="{{ $previewData['title'] }}">
                        @else
                            <div class="flex aspect-[9/16] items-center justify-center p-4 text-center text-cap-l text-lab-sc">{{ __('business/ads.preview.media_placeholder') }}</div>
                        @endif
                        <div class="p-3"><h4 class="line-clamp-2 text-par-m font-bold text-lab-pr2">{{ $previewData['title'] }}</h4><p class="mt-1 line-clamp-3 text-cap-l text-lab-sc">{{ $previewData['content'] }}</p></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div wire:loading.flex wire:target="submitForm,setSourceType,setMediaType,setCtaText" class="fixed right-4 top-4 z-[80] items-center gap-2 rounded-full bg-lab-pr2 px-4 py-2 text-cap-l font-bold text-bg-pr shadow-lg">
        <span class="size-3 animate-spin rounded-full border-2 border-bg-pr/40 border-t-bg-pr"></span>
        Updating...
    </div>
</div>
