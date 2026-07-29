@php
    $createAction = null;

    if(route_is('business.ads.*') && ! route_is('business.ads.create')) {
        $createAction = [
            'href' => route('business.ads.create'),
            'label' => __('business/ads.create_title')
        ];
    }
    elseif(route_is('business.market.*') && ! route_is('business.market.create')) {
        $createAction = [
            'href' => route('business.market.create'),
            'label' => __('business/market.create_title')
        ];
    }
    elseif(route_is('business.jobs.*') && ! route_is('business.jobs.create')) {
        $createAction = [
            'href' => route('business.jobs.create'),
            'label' => __('business/jobs.create_title')
        ];
    }

    $mobileTitle = __('business/labels.business_account');

    if(route_is('business.ads.*')) {
        $mobileTitle = __('business/labels.campaign');
    }
    elseif(route_is('business.market.*')) {
        $mobileTitle = __('business/labels.marketplace');
    }
    elseif(route_is('business.jobs.*')) {
        $mobileTitle = __('business/labels.jobs');
    }
    elseif(route_is('business.wallet.*')) {
        $mobileTitle = __('business/labels.wallet');
    }
    elseif(route_is('business.settings.*')) {
        $mobileTitle = __('business/labels.account_settings');
    }

    $moreActive = (route_is('business.settings.*') || route_is('business.ads.*'));
@endphp

<div class="business-mobile lg:hidden">
    <header class="business-mobile-topbar">
        <a href="{{ route('user.desktop.index') }}" class="business-mobile-icon-btn" aria-label="{{ __('admin/sidebar.home') }}">
            <x-ui-icon name="home-smile" type="line"></x-ui-icon>
        </a>

        <a href="{{ route('business.dashboard.index') }}" class="min-w-0 flex-1">
            <span class="block truncate text-title-3 font-semibold text-lab-pr">
                {{ $mobileTitle }}
            </span>
        </a>

        <div class="flex shrink-0 items-center gap-2">
            @if($createAction)
                <a href="{{ $createAction['href'] }}" class="business-mobile-icon-btn" aria-label="{{ $createAction['label'] }}">
                    <x-ui-icon name="plus" type="solid"></x-ui-icon>
                </a>
            @endif

            <a href="{{ me()->profile_url }}" class="business-mobile-avatar" aria-label="{{ me()->name }}">
                <x-general.avatars.img src="{{ me()->avatar_url }}" class="size-full object-cover" alt="{{ me()->username }}" />
            </a>
        </div>
    </header>

    <input id="business-more-toggle" class="business-more-toggle" type="checkbox">

    <nav class="business-mobile-bottomnav" aria-label="{{ __('business/labels.business_account') }}">
        <a href="{{ route('business.dashboard.index') }}" @class(['business-mobile-nav-item', 'business-mobile-nav-item-active' => route_is('business.dashboard.*')])>
            <span class="business-mobile-nav-icon">
                <x-ui-icon name="dash-02" type="solar"></x-ui-icon>
            </span>
            <span>{{ __('business/labels.overview') }}</span>
        </a>

        <a href="{{ route('business.market.index') }}" @class(['business-mobile-nav-item', 'business-mobile-nav-item-active' => route_is('business.market.*')])>
            <span class="business-mobile-nav-icon">
                <x-ui-icon name="bag-01" type="solar"></x-ui-icon>
            </span>
            <span>{{ __('business/labels.marketplace') }}</span>
        </a>

        <a href="{{ route('business.jobs.index') }}" @class(['business-mobile-nav-item', 'business-mobile-nav-item-active' => route_is('business.jobs.*')])>
            <span class="business-mobile-nav-icon">
                <x-ui-icon name="case-01" type="solar"></x-ui-icon>
            </span>
            <span>{{ __('business/labels.jobs') }}</span>
        </a>

        @if(config('features.wallet.enabled'))
            <a href="{{ route('business.wallet.index') }}" @class(['business-mobile-nav-item', 'business-mobile-nav-item-active' => route_is('business.wallet.*')])>
                <span class="business-mobile-nav-icon">
                    <x-ui-icon name="wallet-01" type="solar"></x-ui-icon>
                </span>
                <span>{{ __('business/labels.wallet') }}</span>
            </a>
        @endif

        <label for="business-more-toggle" @class(['business-mobile-nav-item cursor-pointer', 'business-mobile-nav-item-active' => $moreActive])>
            <span class="business-mobile-nav-icon">
                <x-ui-icon name="dots-horizontal" type="solid"></x-ui-icon>
            </span>
            <span>{{ __('business/labels.more') }}</span>
        </label>
    </nav>

    <label for="business-more-toggle" class="business-mobile-more-backdrop"></label>

    <div class="business-mobile-more-sheet">
        <div class="business-mobile-more-handle"></div>
        <div class="business-mobile-more-list">
            <a href="{{ route('business.ads.index') }}" @class(['business-mobile-more-item', 'business-mobile-more-item-active' => route_is('business.ads.*')])>
                <span><x-ui-icon name="chart-01" type="solar"></x-ui-icon></span>
                <strong>{{ __('business/labels.campaign') }}</strong>
            </a>

            <a href="{{ route('business.settings.index') }}" @class(['business-mobile-more-item', 'business-mobile-more-item-active' => route_is('business.settings.*')])>
                <span><x-ui-icon name="settings-01" type="solar"></x-ui-icon></span>
                <strong>{{ __('business/labels.account_settings') }}</strong>
            </a>

            <a href="{{ route('document.help.index') }}" class="business-mobile-more-item">
                <span><x-ui-icon name="help-01" type="solar"></x-ui-icon></span>
                <strong>{{ __('business/labels.help') }}</strong>
            </a>

            <a href="{{ config('business.links.business_account_guide') }}" class="business-mobile-more-item">
                <span><x-ui-icon name="arrow-up-right" type="line"></x-ui-icon></span>
                <strong>{{ __('business/labels.about_account') }}</strong>
            </a>

            <a href="{{ route('user.auth.logout') }}" class="business-mobile-more-item business-mobile-more-item-danger">
                <span><x-ui-icon name="logout-01" type="solar"></x-ui-icon></span>
                <strong>{{ __('labels.logout') }}</strong>
            </a>
        </div>
    </div>
</div>
