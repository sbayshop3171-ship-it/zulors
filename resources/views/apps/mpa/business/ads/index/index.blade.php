@extends('businessLayout::index')

@section('pageContent')
    <div class="business-ads-page">
        <div class="business-ads-header">
            <div class="min-w-0">
                <span class="business-ads-kicker">{{ __('business/labels.campaign') }}</span>
                <x-page-title titleText="{{ __('business/ads.index_title') }}"></x-page-title>
                <p class="business-ads-caption">{{ __('business/ads.empty_state.index_all.desc') }}</p>
            </div>
        </div>

        <div class="business-ads-tabs">
            <x-tabs.tabs>
                <x-tabs.tab-item :active="$type == 'all'" href="{{ route('business.ads.index', ['type' => 'all']) }}" textLabel="{{ __('business/ads.tabs.all') }}"></x-tabs.tab-item>
                <x-tabs.tab-item :active="$type == 'active'" href="{{ route('business.ads.index', ['type' => 'active']) }}" textLabel="{{ __('business/ads.tabs.active') }}"></x-tabs.tab-item>
                <x-tabs.tab-item :active="$type == 'archived'" href="{{ route('business.ads.index', ['type' => 'archived']) }}" textLabel="{{ __('business/ads.tabs.archived') }}"></x-tabs.tab-item>
            </x-tabs.tabs>
        </div>

        @if($adsList->isNotEmpty())
            <div class="business-ads-grid">
                @foreach ($adsList as $adData)
                    @include('business::ads.parts.index.ad-card', [
                        'adData' => $adData
                    ])
                @endforeach
            </div>
        @else
            @if($type == 'all')
                <div class="business-ads-empty">
                    <span class="business-ads-empty-icon">
                        <x-ui-icon name="chart-01" type="solar"></x-ui-icon>
                    </span>
                    <strong>{{ __('business/ads.empty_state.index_all.title') }}</strong>
                    <p>{{ __('business/ads.empty_state.index_all.desc') }}</p>
                    <a href="{{ route('business.ads.create') }}" class="business-ads-empty-action">
                        {{ __('business/ads.create_title') }}
                    </a>
                </div>
            @elseif($type == 'active')
                <div class="business-ads-empty">
                    <span class="business-ads-empty-icon">
                        <x-ui-icon name="chart-01" type="solar"></x-ui-icon>
                    </span>
                    <strong>{{ __('business/ads.empty_state.index_active.title') }}</strong>
                    <p>{{ __('business/ads.empty_state.index_active.desc') }}</p>
                    <a href="{{ route('business.ads.create') }}" class="business-ads-empty-action">
                        {{ __('business/ads.create_title') }}
                    </a>
                </div>
            @else
                <div class="business-ads-empty">
                    <span class="business-ads-empty-icon">
                        <x-ui-icon name="chart-01" type="solar"></x-ui-icon>
                    </span>
                    <strong>{{ __('business/ads.empty_state.index_archived.title') }}</strong>
                    <p>{{ __('business/ads.empty_state.index_archived.desc') }}</p>
                </div>
            @endif
        @endif

        @unless($adsList->isEmpty())
            <div class="business-ads-pagination">
                {{ $adsList->onEachSide(1)->withQueryString()->links('pagination.index') }}
            </div>
        @endif
    </div>
@endsection
