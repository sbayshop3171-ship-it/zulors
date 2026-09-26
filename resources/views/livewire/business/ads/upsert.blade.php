<div
    class="business-ad-builder min-w-0 max-w-full overflow-x-hidden overscroll-x-none"
    x-data="{
        previewOpen: false,
        sourceType: @js($formData['source_type'] ?? 'creative'),
        mediaType: @js($formData['media_type'] ?? 'image'),
        ctaType: @js(($formData['cta_type'] ?? (($formData['source_type'] ?? 'creative') === 'post' ? 'NO_BUTTON' : 'SEND_MESSAGE'))),
        noButtonLabel: @js(__('business/ads.form.cta_presets.no_button')),
        preview: {
            title: @js($previewData['title']),
            content: @js($previewData['content']),
            targetUrl: @js($previewData['target_url']),
            ctaText: @js($previewData['cta_text']),
            mediaType: @js($previewData['media_type']),
            mediaUrl: @js($previewData['media_url']),
        },
        updatePreview(key, value) {
            this.preview[key] = value;
        },
        previewFile(file) {
            if (!file) return;
            if (this.preview.mediaUrl && this.preview.mediaUrl.startsWith('blob:')) {
                URL.revokeObjectURL(this.preview.mediaUrl);
            }
            this.preview.mediaUrl = URL.createObjectURL(file);
            this.preview.mediaType = file.type.startsWith('video/') ? 'video' : 'image';
        }
    }">
    <div class="grid min-w-0 grid-cols-1 items-start gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(320px,460px)] lg:gap-6">
        <aside class="order-1 min-w-0 lg:order-2">
            <div class="lg:sticky lg:top-6">
                <div class="mb-3 flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between sm:gap-3">
                    <div class="min-w-0">
                        <h3 class="text-par-l font-bold text-lab-pr2">{{ __('business/ads.preview.title') }}</h3>
                        <p class="text-par-s text-lab-sc">{{ __('business/ads.preview.caption') }}</p>
                    </div>
                    @if(($formData['source_type'] ?? 'creative') !== 'post')
                        <button type="button" x-on:click="previewOpen = true" class="w-full max-w-full rounded-lg bg-fill-qt px-3 py-2 text-cap-l font-bold text-lab-pr2 transition-opacity hover:opacity-80 sm:w-auto sm:shrink-0">
                            See all previews
                        </button>
                    @endif
                </div>

                @if(($formData['source_type'] ?? 'creative') === 'post')
                    @if($selectedSourcePost)
                        @php
                            $previewPostMedia = $selectedSourcePost->media->first();
                        @endphp

                        <article class="w-full max-w-full text-lab-pr2">
                            <div class="mb-4 flex items-center gap-3">
                                <img src="{{ me()->avatar_url }}" alt="{{ me()->name }}" class="size-10 rounded-full object-cover">
                                <div class="min-w-0">
                                    <strong class="block truncate text-par-s font-bold">{{ me()->name }}</strong>
                                    <span class="block text-cap-l text-lab-sc">{{ $selectedSourcePost->type->label() }}</span>
                                </div>
                            </div>

                            @if(filled($selectedSourcePost->title))
                                <h4 class="mb-2 break-words text-par-l font-bold">{{ $selectedSourcePost->title }}</h4>
                            @endif

                            @if(filled($selectedSourcePost->content))
                                <p class="mb-4 whitespace-pre-line break-words text-par-m leading-relaxed">{{ $selectedSourcePost->content }}</p>
                            @endif

                            @if($previewPostMedia && $previewPostMedia->source_url)
                                @if($previewPostMedia->type->isVideo())
                                    <video
                                        class="business-ad-preview-media block h-auto max-w-full"
                                        src="{{ $previewPostMedia->source_url }}"
                                        poster="{{ $previewPostMedia->thumbnail_url ?: asset(config('ads.ad.default_preview')) }}"
                                        controls
                                        muted
                                        playsinline></video>
                                @else
                                    <img
                                        class="business-ad-preview-media block h-auto max-w-full"
                                        src="{{ $previewPostMedia->source_url }}"
                                        alt="{{ $selectedSourcePost->title ?: $selectedSourcePost->content }}">
                                @endif
                            @elseif($previewPostMedia && $previewPostMedia->thumbnail_url)
                                <img
                                    class="business-ad-preview-media block h-auto max-w-full"
                                    src="{{ $previewPostMedia->thumbnail_url }}"
                                    alt="{{ $selectedSourcePost->title ?: $selectedSourcePost->content }}">
                            @elseif(blank($selectedSourcePost->content) && blank($selectedSourcePost->title))
                                <p class="text-par-s text-lab-sc">{{ __('business/ads.preview.post_placeholder') }}</p>
                            @endif

                            @php
                                $previewCtaType = $formData['cta_type'] ?? 'NO_BUTTON';
                                $previewDestinationType = $formData['destination_type'] ?? 'external_url';
                                $previewHasDerivedDestination = $previewDestinationType === 'profile'
                                    || ($previewDestinationType === 'internal_post' && $selectedSourcePost)
                                    || (in_array($previewDestinationType, ['phone', 'whatsapp'], true) && filled($adData->user?->phone));
                                $previewHasCta = $previewCtaType !== 'NO_BUTTON'
                                    && ($formData['cta_text'] ?? '') !== __('business/ads.form.cta_presets.no_button')
                                    && filled($formData['cta_text'] ?? '')
                                    && ($previewHasDerivedDestination || filled($formData['target_url'] ?? ''));
                            @endphp

                            @if($previewHasCta)
                                <button type="button" class="mt-4 block w-full rounded-xl bg-lab-pr2 px-4 py-3 text-center text-par-s font-bold text-bg-pr">
                                    {{ $formData['cta_text'] }}
                                </button>
                            @endif
                        </article>
                    @else
                        <div class="py-12 text-center text-par-s text-lab-sc">
                            {{ __('business/ads.preview.post_placeholder') }}
                        </div>
                    @endif
                @else
                    <div class="mx-auto w-full max-w-[340px] overflow-hidden rounded-xl border border-bord-pr bg-bg-pr shadow-xs">
                        <div class="p-3">
                            <div class="mb-3 flex items-center gap-3">
                                <img src="{{ me()->avatar_url }}" alt="{{ me()->name }}" class="size-10 rounded-full object-cover">
                                <div class="min-w-0">
                                    <strong class="block truncate text-par-s font-bold text-lab-pr2">{{ me()->name }}</strong>
                                    <span class="block text-cap-l font-semibold text-lab-sc">{{ __('api/labels.ad') }}</span>
                                </div>
                            </div>

                            <p class="mb-3 whitespace-pre-line text-par-s leading-relaxed text-lab-pr2" x-text="preview.content"></p>

                            <div class="mb-3 overflow-hidden rounded-xl bg-fill-fv">
                                <video
                                    x-show="preview.mediaType === 'video' && preview.mediaUrl"
                                    class="aspect-video w-full object-cover"
                                    x-bind:src="preview.mediaUrl"
                                    poster="{{ $previewData['thumbnail_url'] ?: asset(config('ads.ad.default_preview')) }}"
                                    controls
                                    muted
                                    playsinline></video>
                                <img
                                    x-show="preview.mediaType !== 'video' && preview.mediaUrl"
                                    class="aspect-video w-full object-cover"
                                    x-bind:src="preview.mediaUrl"
                                    x-bind:alt="preview.title">
                                <div x-show="!preview.mediaUrl" class="flex aspect-video items-center justify-center px-6 text-center text-par-s font-semibold text-lab-sc">
                                    {{ __('business/ads.preview.media_placeholder') }}
                                </div>
                            </div>

                            <div class="rounded-xl bg-fill-fv p-3">
                                <h4 class="line-clamp-2 text-par-m font-bold text-lab-pr2" x-text="preview.title"></h4>
                                <p x-show="preview.targetUrl" class="mt-1 truncate text-cap-l font-semibold text-lab-sc" x-text="preview.targetUrl"></p>
                                <div x-show="ctaType !== 'NO_BUTTON' && preview.ctaText !== noButtonLabel" class="mt-3">
                                    <button type="button" class="h-10 w-full rounded-xl bg-lab-pr2 px-4 text-par-s font-bold text-bg-pr" x-text="preview.ctaText"></button>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </aside>

        <form class="order-2 min-w-0 lg:order-1" wire:submit.prevent="submitForm">
            @csrf

            <x-accordion.form title="{{ __('business/ads.form.source_type') }}">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-1">
                    <button
                        type="button"
                        wire:click="setSourceType('post')"
                        x-on:click="sourceType = 'post'; ctaType = 'NO_BUTTON'"
                        x-bind:class="sourceType === 'post' ? 'border-brand-900 bg-fill-qt' : 'border-bord-pr bg-bg-pr'"
                        class="rounded-2xl border p-4 text-left smoothing">
                        <strong class="block text-par-s text-lab-pr2">{{ __('business/ads.form.boost_post') }}</strong>
                        <span class="mt-1 block text-cap-l text-lab-sc">{{ __('business/ads.form.boost_post_helper') }}</span>
                    </button>

                    <button
                        type="button"
                        wire:click="setSourceType('creative')"
                        x-on:click="sourceType = 'creative'; ctaType = 'SEND_MESSAGE'"
                        x-bind:class="sourceType === 'creative' ? 'border-brand-900 bg-fill-qt' : 'border-bord-pr bg-bg-pr'"
                        class="rounded-2xl border p-4 text-left smoothing">
                        <strong class="block text-par-s text-lab-pr2">{{ __('business/ads.form.create_new_ad') }}</strong>
                        <span class="mt-1 block text-cap-l text-lab-sc">{{ __('business/ads.form.create_new_ad_helper') }}</span>
                    </button>
                </div>
            </x-accordion.form>

            @if(($formData['source_type'] ?? 'creative') === 'post')
                <x-accordion.form title="{{ __('business/ads.form.source_post') }}">
                    <div class="mb-5 space-y-3">
                        <div class="relative">
                            <x-form.text-input
                                labelText="{{ __('business/ads.form.search_posts') }}"
                                wire:model.live.debounce.300ms="boostPostSearch"
                                name="boostPostSearch"
                                placeholder="{{ __('business/ads.form.search_posts_placeholder') }}">
                                <x-slot:feedbackInfo>
                                    {{ __('business/ads.form.search_posts_helper') }}
                                </x-slot:feedbackInfo>
                            </x-form.text-input>

                            @if($boostPostSearch !== '')
                                <button
                                    type="button"
                                    wire:click="clearBoostPostSearch"
                                    class="absolute right-3 top-[44px] rounded-full border border-bord-pr bg-bg-pr px-2 py-1 text-cap-s font-semibold text-lab-sc transition hover:bg-fill-fv"
                                >
                                    {{ __('business/ads.form.clear_search') }}
                                </button>
                            @endif
                        </div>

                        <button
                            type="button"
                            wire:click="openBoostPostPicker"
                            class="flex w-full items-center justify-between rounded-xl border border-bord-pr bg-bg-pr px-3 py-3 text-left transition hover:border-brand-900 hover:bg-fill-fv"
                        >
                            <span>
                                <strong class="block text-par-s font-semibold text-lab-pr2">{{ __('business/ads.form.select_a_post') }}</strong>
                                <span class="mt-0.5 block text-cap-l text-lab-sc">{{ __('business/ads.form.browse_all_posts_helper') }}</span>
                            </span>
                            <svg class="size-5 shrink-0 text-lab-sc" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.168l3.71-3.938a.75.75 0 1 1 1.08 1.04l-4.25 4.51a.75.75 0 0 1-1.08 0l-4.25-4.51a.75.75 0 1 1 .02-1.06Z" clip-rule="evenodd"></path>
                            </svg>
                        </button>

                        @if($boostPostPickerOpen)
                            <div class="rounded-2xl border border-bord-pr bg-bg-pr p-3 shadow-sm">
                                <div class="mb-3 flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between sm:gap-3">
                                    <div class="min-w-0">
                                        <strong class="block text-par-s font-bold text-lab-pr2">{{ __('business/ads.form.all_posts') }}</strong>
                                        <span class="mt-0.5 block text-cap-l text-lab-sc">{{ __('business/ads.form.browse_all_posts_helper') }}</span>
                                    </div>
                                    <button type="button" wire:click="closeBoostPostPicker" class="self-start rounded-lg border border-bord-pr px-2 py-1 text-cap-s font-semibold text-lab-sc hover:bg-fill-fv sm:shrink-0">
                                        {{ __('business/ads.form.close_picker') }}
                                    </button>
                                </div>

                                <div class="mb-3 grid gap-2 sm:grid-cols-[minmax(0,1fr)_auto]">
                                    <input
                                        type="search"
                                        wire:model.live.debounce.300ms="boostPostPickerSearch"
                                        class="h-11 w-full rounded-xl border border-bord-pr bg-fill-fv px-3 text-par-s text-lab-pr2 outline-hidden focus:border-brand-900"
                                        placeholder="{{ __('business/ads.form.search_all_posts_placeholder') }}"
                                        aria-label="{{ __('business/ads.form.search_all_posts') }}"
                                    >
                                    <select wire:model.live="boostPostPickerSort" class="h-11 w-full min-w-0 rounded-xl border border-bord-pr bg-fill-fv px-3 text-par-s text-lab-pr2 outline-hidden focus:border-brand-900 sm:w-auto" aria-label="{{ __('business/ads.form.sort_posts') }}">
                                        <option value="recent">{{ __('business/ads.form.sort_recent') }}</option>
                                        <option value="reach">{{ __('business/ads.form.sort_reach') }}</option>
                                    </select>
                                </div>

                                <div class="max-h-[28rem] space-y-2 overflow-y-auto pr-1">
                                    @forelse($boostPickerPosts as $boostPost)
                                        @php
                                            $isPickerSelected = (int) ($selectedSourcePost?->id ?? 0) === (int) $boostPost->id;
                                            $pickerMedia = $boostPost->media->first();
                                        @endphp
                                        <button
                                            type="button"
                                            wire:key="boost-post-picker-{{ $boostPost->id }}"
                                            wire:click="chooseBoostPost({{ $boostPost->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="chooseBoostPost"
                                            class="flex w-full min-w-0 flex-wrap items-start gap-3 rounded-xl border p-2.5 text-left transition hover:border-brand-900 hover:bg-fill-fv {{ $isPickerSelected ? 'border-brand-900 bg-fill-qt' : 'border-bord-pr' }}"
                                            aria-pressed="{{ $isPickerSelected ? 'true' : 'false' }}"
                                        >
                                            @if($pickerMedia && $pickerMedia->source_url)
                                                <img src="{{ $pickerMedia->type->isVideo() ? ($pickerMedia->thumbnail_url ?: $pickerMedia->source_url) : $pickerMedia->source_url }}" alt="{{ $boostPost->title ?: $boostPost->content }}" class="size-12 shrink-0 rounded-lg object-cover">
                                            @else
                                                <span class="flex size-12 shrink-0 items-center justify-center rounded-lg bg-fill-fv text-cap-s font-bold text-lab-sc">{{ strtoupper($boostPost->type->value[0] ?? 'P') }}</span>
                                            @endif
                                            <span class="min-w-0 flex-1">
                                                <strong class="block truncate text-par-s font-semibold text-lab-pr2">{{ $boostPost->title ?: str($boostPost->content)->limit(72) }}</strong>
                                                <span class="mt-1 block truncate text-cap-l text-lab-sc">
                                                    {{ $boostPost->type->label() }} · {{ number_format((int) $boostPost->views_count) }} views · {{ number_format((int) $boostPost->comments_count) }} comments
                                                </span>
                                            </span>
                                            @if($isPickerSelected)
                                                <span class="max-w-full shrink-0 rounded-full bg-brand-900 px-2 py-1 text-cap-s font-bold text-bg-pr">{{ __('business/ads.form.selected_post') }}</span>
                                            @endif
                                        </button>
                                    @empty
                                        <div class="rounded-xl border border-dashed border-bord-pr bg-fill-fv p-4 text-center text-par-s text-lab-sc">
                                            {{ __('business/ads.form.no_posts_available') }}
                                        </div>
                                    @endforelse
                                </div>

                                @if($boostPickerHasMore)
                                    <button type="button" wire:click="loadMoreBoostPosts" wire:loading.attr="disabled" wire:target="loadMoreBoostPosts" class="mt-3 w-full rounded-xl border border-bord-pr px-3 py-2.5 text-par-s font-semibold text-lab-pr2 transition hover:bg-fill-fv">
                                        {{ __('business/ads.form.load_more_posts') }}
                                    </button>
                                @endif
                            </div>
                        @endif

                        @if($boostPostSearch === '')
                            <div class="rounded-xl bg-fill-fv px-3 py-2 text-cap-l font-semibold text-lab-sc">
                                {{ __('business/ads.form.recent_posts') }}
                            </div>
                        @elseif($boostPostSearch !== '' && $boostablePosts->isEmpty())
                            <div class="rounded-xl border border-dashed border-bord-pr bg-fill-fv p-4 text-center text-par-s text-lab-sc">
                                {{ __('business/ads.form.no_matching_posts') }}
                            </div>
                        @endif
                    </div>

                    <div class="mb-5 space-y-3">
                        @php
                            $searching = filled($boostPostSearch) && mb_strlen(trim($boostPostSearch), 'UTF-8') >= 2;
                        @endphp

                        @if($searching)
                            <div wire:loading.delay wire:target="boostPostSearch" class="rounded-xl border border-bord-pr bg-fill-fv px-3 py-2 text-cap-l font-semibold text-lab-sc">
                                {{ __('business/ads.form.searching') }}...
                            </div>
                        @endif

                        @forelse($boostablePosts as $boostPost)
                            @php
                                $isSelected = (int) ($selectedSourcePost?->id ?? 0) === (int) $boostPost->id;
                                $media = $boostPost->media->first();
                            @endphp

                            <button
                                type="button"
                                wire:key="boost-post-{{ $boostPost->id }}"
                                wire:click="chooseBoostPost({{ $boostPost->id }})"
                                wire:loading.attr="disabled"
                                wire:target="chooseBoostPost"
                                class="flex w-full min-w-0 items-start gap-3 rounded-2xl border p-3 text-left transition hover:border-brand-900 hover:bg-fill-fv focus:outline-none focus:ring-2 focus:ring-brand-900 {{ $isSelected ? 'border-brand-900 bg-fill-qt' : 'border-bord-pr bg-bg-pr' }}"
                                aria-pressed="{{ $isSelected ? 'true' : 'false' }}"
                            >
                                @if($media && $media->source_url)
                                    <div class="h-16 w-16 shrink-0 overflow-hidden rounded-xl bg-fill-fv">
                                        @if($media->type->isVideo())
                                            <img src="{{ $media->thumbnail_url ?: $media->source_url }}" alt="{{ $boostPost->title ?: $boostPost->content }}" class="h-full w-full object-cover">
                                        @else
                                            <img src="{{ $media->source_url }}" alt="{{ $boostPost->title ?: $boostPost->content }}" class="h-full w-full object-cover">
                                        @endif
                                    </div>
                                @else
                                    <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-xl bg-fill-fv text-cap-s font-bold text-lab-sc">
                                        {{ strtoupper($boostPost->type->value[0] ?? 'P') }}
                                    </div>
                                @endif

                                <div class="min-w-0 flex-1">
                                    <div class="flex min-w-0 flex-wrap items-center justify-between gap-2">
                                        <span class="text-cap-l font-semibold text-lab-sc">{{ $boostPost->type->label() }}</span>
                                        @if($isSelected)
                                            <span class="max-w-full rounded-full bg-brand-900 px-2 py-1 text-cap-s font-bold text-bg-pr">
                                                {{ __('business/ads.form.selected_post') }}
                                            </span>
                                        @endif
                                    </div>

                                    <p class="mt-1 line-clamp-2 text-par-s font-semibold text-lab-pr2">
                                        {{ $boostPost->title ?: str($boostPost->content)->limit(90) }}
                                    </p>

                                    @if(filled($boostPost->content) && $boostPost->title && $boostPost->content !== $boostPost->title)
                                        <p class="mt-1 line-clamp-2 text-cap-l text-lab-sc">
                                            {{ str($boostPost->content)->limit(90) }}
                                        </p>
                                    @endif

                                    @if(filled($boostPost->created_at))
                                        <p class="mt-1 text-cap-l text-lab-sc">
                                            {{ $boostPost->created_at->getDate() }}
                                        </p>
                                    @endif
                                </div>
                            </button>
                        @empty
                            <div class="rounded-xl border border-dashed border-bord-pr bg-fill-fv p-4 text-center text-par-s text-lab-sc">
                                {{ __('business/ads.form.no_matching_posts') }}
                            </div>
                        @endforelse
                    </div>

                    @if($selectedSourcePost)
                        @php
                            $selectedMedia = $selectedSourcePost->media->first();
                        @endphp

                        <div class="rounded-2xl border border-bord-pr bg-fill-fv p-4">
                            <div class="mb-2 flex flex-wrap items-start justify-between gap-2">
                                <strong class="block text-par-s font-bold text-lab-pr2">{{ __('business/ads.form.selected_post') }}</strong>
                                <div class="flex max-w-full flex-wrap justify-end gap-2">
                                    <button type="button" wire:click="clearBoostPostSelection" class="rounded-lg border border-bord-pr bg-bg-pr px-2 py-1 text-cap-s font-semibold text-lab-sc transition hover:bg-fill-qt">
                                        {{ __('business/ads.form.clear_selection') }}
                                    </button>
                                    <button type="button" wire:click="clearBoostPostSearch" class="rounded-lg border border-bord-pr bg-bg-pr px-2 py-1 text-cap-s font-semibold text-lab-sc transition hover:bg-fill-qt">
                                        {{ __('business/ads.form.change_post') }}
                                    </button>
                                </div>
                            </div>

                            <div class="flex min-w-0 items-start gap-3">
                                @if($selectedMedia && $selectedMedia->source_url)
                                    <img src="{{ $selectedMedia->type->isVideo() ? ($selectedMedia->thumbnail_url ?: $selectedMedia->source_url) : $selectedMedia->source_url }}" alt="{{ $selectedSourcePost->title ?: $selectedSourcePost->content }}" class="h-16 w-16 rounded-xl object-cover">
                                @endif

                                <div class="min-w-0">
                                    <strong class="block text-par-s text-lab-pr2">{{ $selectedSourcePost->type->label() }}</strong>
                                    <p class="mt-1 line-clamp-3 text-par-s text-lab-sc">{{ $selectedSourcePost->title ?: $selectedSourcePost->content }}</p>
                                </div>
                            </div>
                        </div>
                    @endif
                </x-accordion.form>
            @else
                <x-accordion.form title="{{ __('business/ads.form.base_info') }}">
                    <div class="mb-6">
                        <x-form.text-input
                            wire:model.live.debounce.100ms="formData.title"
                            x-on:input="updatePreview('title', $event.target.value)"
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
                            wire:model.live.debounce.100ms="formData.content"
                            x-on:input="updatePreview('content', $event.target.value)"
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
                            x-on:click="mediaType = 'image'"
                            x-bind:class="mediaType === 'image' ? 'border-brand-900 bg-fill-qt text-brand-900' : 'border-bord-pr text-lab-sc'"
                            class="rounded-xl border p-3 text-center text-par-s font-bold smoothing">
                            {{ __('labels.image') }}
                        </button>
                        <button
                            type="button"
                            wire:click="setMediaType('video')"
                            x-on:click="mediaType = 'video'"
                            x-bind:class="mediaType === 'video' ? 'border-brand-900 bg-fill-qt text-brand-900' : 'border-bord-pr text-lab-sc'"
                            class="rounded-xl border p-3 text-center text-par-s font-bold smoothing">
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
                                    <input
                                        x-ref="input"
                                        wire:model="creative"
                                        x-on:change="previewFile($event.target.files[0])"
                                        x-bind:accept="mediaType === 'video' ? 'video/mp4,video/webm,video/quicktime' : 'image/*'"
                                        type="file"
                                        class="hidden">
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

            <x-accordion.form title="{{ __('business/ads.form.campaign_controls') }}">
                <div class="mb-6">
                    <label for="formDataObjective" class="mb-1 block text-par-s font-normal text-lab-pr3">{{ __('business/ads.form.objective') }} *</label>
                    <select id="formDataObjective" wire:model="formData.objective" class="h-11 w-full rounded-xl border border-bord-pr bg-bg-pr px-3 text-par-s text-lab-pr2">
                        @foreach(__('business/ads.form.objectives') as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <fieldset class="mb-6">
                    <legend class="mb-2 block text-par-s font-normal text-lab-pr3">{{ __('business/ads.form.placements') }} *</legend>
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                        @foreach(['feed', 'reels', 'sidebar'] as $placement)
                            <label class="flex items-center gap-2 rounded-xl border border-bord-pr px-3 py-2.5 text-par-s text-lab-pr2">
                                <input type="checkbox" value="{{ $placement }}" wire:model="formData.placement_flags" class="size-4 rounded border-bord-pr text-brand-900">
                                <span>{{ __('business/ads.form.' . $placement) }}</span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                <div class="mb-6">
                    <label for="formDataDestinationType" class="mb-1 block text-par-s font-normal text-lab-pr3">{{ __('business/ads.form.destination_type') }} *</label>
                    <select id="formDataDestinationType" wire:model="formData.destination_type" class="h-11 w-full rounded-xl border border-bord-pr bg-bg-pr px-3 text-par-s text-lab-pr2">
                        @foreach(__('business/ads.form.destinations') as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="mb-6 grid gap-4 sm:grid-cols-2">
                    <label class="block text-par-s font-normal text-lab-pr3">{{ __('business/ads.form.start_at') }}
                        <input
                            type="datetime-local"
                            wire:model.live="formData.start_at"
                            step="900"
                            autocomplete="off"
                            onclick="if (this.showPicker) this.showPicker()"
                            class="mt-1 h-11 w-full rounded-xl border border-bord-pr bg-bg-pr px-3 text-par-s text-lab-pr2">
                    </label>
                    <label class="block text-par-s font-normal text-lab-pr3">{{ __('business/ads.form.end_at') }}
                        <input
                            type="datetime-local"
                            wire:model.live="formData.end_at"
                            min="{{ $formData['start_at'] ?? '' }}"
                            step="900"
                            autocomplete="off"
                            onclick="if (this.showPicker) this.showPicker()"
                            class="mt-1 h-11 w-full rounded-xl border border-bord-pr bg-bg-pr px-3 text-par-s text-lab-pr2">
                    </label>
                </div>
                <p class="-mt-3 mb-6 text-cap-l text-lab-sc">{{ __('business/ads.form.schedule_helper') }}</p>

                <div class="mb-6 grid gap-4 sm:grid-cols-2">
                    <label class="block text-par-s font-normal text-lab-pr3">{{ __('business/ads.form.frequency_cap') }}
                        <input type="number" min="1" max="100" wire:model="formData.frequency_cap" class="mt-1 h-11 w-full rounded-xl border border-bord-pr bg-bg-pr px-3 text-par-s text-lab-pr2">
                    </label>
                    <label class="block text-par-s font-normal text-lab-pr3">{{ __('business/ads.form.target_category') }}
                        @php
                            $targetCategories = __('business/ads.form.target_categories');
                        @endphp
                        <select wire:model="formData.target_category" class="mt-1 h-11 w-full rounded-xl border border-bord-pr bg-bg-pr px-3 text-par-s text-lab-pr2">
                            <option value="">{{ __('business/ads.form.target_category_placeholder') }}</option>
                            @if(filled($formData['target_category'] ?? null) && ! array_key_exists($formData['target_category'], $targetCategories))
                                <option value="{{ $formData['target_category'] }}">{{ $formData['target_category'] }}</option>
                            @endif
                            @foreach($targetCategories as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <span class="mt-1 block text-cap-l text-lab-sc">{{ __('business/ads.form.target_category_helper') }}</span>
                    </label>
                </div>
            </x-accordion.form>

            @php
                $ctaOptions = [
                    ['type' => 'NO_BUTTON', 'label' => __('business/ads.form.cta_presets.no_button')],
                    ['type' => 'LEARN_MORE', 'label' => __('business/ads.form.cta_presets.learn_more')],
                    ['type' => 'SIGN_UP', 'label' => __('business/ads.form.cta_presets.sign_up')],
                    ['type' => 'SEND_MESSAGE', 'label' => __('business/ads.form.cta_presets.send_message')],
                    ['type' => 'CALL_NOW', 'label' => __('business/ads.form.cta_presets.call_now')],
                    ['type' => 'ORDER_NOW', 'label' => 'Order Now'],
                    ['type' => 'BOOK_NOW', 'label' => 'Book Now'],
                    ['type' => 'GET_OFFER', 'label' => 'Get Offer'],
                    ['type' => 'WHATSAPP_MESSAGE', 'label' => 'WhatsApp Message'],
                ];
            @endphp
            <x-accordion.form title="{{ __('business/ads.form.cta') }}">
                <div
                    class="mb-6"
                    wire:key="cta-selector-{{ $formData['source_type'] ?? 'creative' }}-{{ $formData['cta_type'] ?? 'SEND_MESSAGE' }}-{{ md5((string) ($formData['cta_text'] ?? '')) }}"
                    x-data="{
                        open: false,
                        selected: @js(($formData['cta_type'] ?? '') === 'NO_BUTTON' ? __('business/ads.form.cta_presets.no_button') : ($formData['cta_text'] ?: __('business/ads.form.cta_presets.send_message'))),
                        selectedType: @js($formData['cta_type'] ?: 'SEND_MESSAGE'),
                        selectOption(option) {
                            this.selected = option.label;
                            this.selectedType = option.type;
                            this.open = false;
                            updatePreview('ctaText', option.label);
                            this.ctaType = option.type;
                            $wire.set('formData.cta_type', option.type);
                            $wire.set('formData.cta_text', option.label);
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
                        @foreach($ctaOptions as $ctaOption)
                            <button
                                type="button"
                                role="option"
                                x-on:click="selectOption(@js($ctaOption))"
                                x-bind:aria-selected="selectedType === @js($ctaOption['type'])"
                                class="flex w-full items-center justify-between gap-3 px-3 py-2.5 text-left transition-colors hover:bg-fill-fv">
                                <span class="min-w-0">
                                    <strong class="block text-par-s font-semibold text-lab-pr2">{{ $ctaOption['label'] }}</strong>
                                    @if($ctaOption['type'] === 'SEND_MESSAGE')
                                        <small class="mt-0.5 block text-cap-l leading-snug text-lab-sc">Get messages on Messenger, Instagram and WhatsApp</small>
                                    @endif
                                </span>
                                <span class="flex size-5 shrink-0 items-center justify-center rounded-full border-2 border-lab-sc" x-bind:class="{ 'border-brand-900 bg-brand-900': selectedType === @js($ctaOption['type']) }">
                                    <span x-show="selectedType === @js($ctaOption['type'])" class="size-2 rounded-full bg-bg-pr"></span>
                                </span>
                            </button>
                        @endforeach
                    </div>
                    <input type="hidden"
                        name="formData.cta_text"
                            wire:model.live.debounce.100ms="formData.cta_text">
                    <p class="mt-1 text-cap-l text-lab-sc">{{ __('business/ads.form.cta_helper') }}</p>

                    @if(($formData['cta_type'] ?? '') !== 'NO_BUTTON' && ($formData['cta_text'] ?? '') !== __('business/ads.form.cta_presets.no_button'))
                        <div class="mb-6 mt-4">
                            <label for="formDataDestinationType" class="mb-1 block text-par-s font-normal text-lab-pr3">{{ __('business/ads.form.destination_type') }} *</label>
                            <select id="formDataDestinationType" wire:model="formData.destination_type" class="h-11 w-full rounded-xl border border-bord-pr bg-bg-pr px-3 text-par-s text-lab-pr2">
                                @foreach(__('business/ads.form.destinations') as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    @if(($formData['cta_type'] ?? '') !== 'NO_BUTTON' && ($formData['cta_text'] ?? '') !== __('business/ads.form.cta_presets.no_button') && (! in_array($formData['destination_type'] ?? 'external_url', ['profile', 'internal_post'], true) && (! in_array($formData['destination_type'] ?? 'external_url', ['phone', 'whatsapp'], true) || blank($adData->user?->phone))))
                        <div class="mt-4">
                            <x-form.text-input
                                labelText="{{ __('business/ads.form.target_url') }} *"
                                inputType="url"
                                wire:model.live.debounce.100ms="formData.target_url"
                                x-on:input="updatePreview('targetUrl', $event.target.value)"
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
                    <label for="targetTopicInput" class="mb-1 block text-par-s font-normal text-lab-pr3">
                        {{ __('business/ads.form.detailed_targeting') }}
                    </label>
                    <div
                        x-data="{ input: '' }"
                        x-on:keydown.enter.prevent="if (input.trim()) { $wire.addTargetTopic(input); input = ''; }"
                        x-on:keydown.backspace="if (!input) $wire.removeLastTargetTopic()"
                        class="min-h-11 rounded-xl border border-bord-pr bg-bg-pr px-3 py-2 transition-colors focus-within:border-brand-900">
                        <div class="flex flex-wrap items-center gap-2">
                            @foreach($targetTopicChips as $topic)
                                <span wire:key="target-topic-{{ md5($topic) }}" class="inline-flex max-w-full items-center gap-1 rounded-lg bg-brand-900/10 px-2.5 py-1.5 text-par-s text-brand-900">
                                    <span class="truncate">{{ $topic }}</span>
                                    <button
                                        type="button"
                                        wire:click="removeTargetTopic(@js($topic))"
                                        aria-label="{{ __('business/ads.form.remove_target_topic', ['topic' => $topic]) }}"
                                        class="inline-flex size-5 shrink-0 items-center justify-center rounded-full text-brand-900 transition-colors hover:bg-brand-900/15">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </span>
                            @endforeach
                            <input
                                id="targetTopicInput"
                                x-model="input"
                                type="text"
                                autocomplete="off"
                                placeholder="{{ __('business/ads.form.detailed_targeting_placeholder') }}"
                                class="min-w-40 flex-1 border-0 bg-transparent px-1 py-1 text-par-s text-lab-pr2 outline-hidden placeholder:text-lab-sc">
                        </div>
                    </div>
                    <input type="hidden" wire:model="formData.target_topics" name="formData.target_topics">
                    <p class="mt-1 text-cap-l text-lab-sc">{{ __('business/ads.form.detailed_targeting_helper') }}</p>
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

    @if(($formData['source_type'] ?? 'creative') !== 'post')
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
    @endif

    <div wire:loading.flex wire:target="submitForm,setSourceType,setMediaType,setCtaText" class="fixed left-4 right-4 top-4 z-[80] items-center justify-center gap-2 rounded-full bg-lab-pr2 px-4 py-2 text-center text-cap-l font-bold text-bg-pr shadow-lg sm:left-auto sm:right-4">
        <span class="size-3 animate-spin rounded-full border-2 border-bg-pr/40 border-t-bg-pr"></span>
        Updating...
    </div>
</div>
