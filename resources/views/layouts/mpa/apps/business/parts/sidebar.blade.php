<x-sidebar.container>
    <x-sidebar.navbar>
        <div class="mb-12">
            <a href="{{ route('business.dashboard.index') }}" class="flex items-center gap-2">
                <img class="h-5" src="{{ $logotypeUrl }}" alt="Image">
                <span class="font-bold text-lab-pr">
                    {{ __('business/labels.business_account') }}
                </span>
            </a>
        </div>
        <x-sidebar.navlist>
            <x-sidebar.navlist-item
                href="{{ route('user.desktop.index') }}"
                iconName="home-01"
                iconType="solar"
                :trailingIcon="true"
                trailingIconName="arrow-up-right"
                trailingIconType="line"
                target="_blank"
            text="{{ __('admin/sidebar.home') }}"/>
            <x-sidebar.navlist-div/>
            <x-sidebar.navlist-item
                href="{{ route('business.dashboard.index') }}"
                iconName="dash-02"
                iconType="solar"
                :current="route_is('business.dashboard.*')"
            text="{{ __('business/labels.overview') }}"/>

            <x-sidebar.navlist-item
                href="{{ route('business.ads.index') }}"
                iconName="chart-01"
                iconType="solar"
                :current="route_is('business.ads.*')"
            text="{{ __('business/labels.campaign') }}"/>

            <x-sidebar.navlist-item
                href="{{ route('business.market.index') }}"
                iconName="bag-01"
                iconType="solar"
                :current="route_is('business.market.*')"
            text="{{ __('business/labels.marketplace') }}"/>

            <x-sidebar.navlist-item
                href="{{ route('business.jobs.index') }}"
                iconName="case-01"
                iconType="solar"
                :current="route_is('business.jobs.*')"
            text="{{ __('business/labels.jobs') }}"/>

            <x-sidebar.navlist-div/>

            @if(config('features.wallet.enabled'))
                <x-sidebar.navlist-item
                    href="{{ route('business.wallet.index') }}"
                    iconName="wallet-01"
                    iconType="solar"
                    :current="route_is('business.wallet.*')"
                text="{{ __('business/labels.wallet') }}"/>
            @endif

            <x-sidebar.navlist-item
                href="{{ route('business.settings.index') }}"
                iconName="settings-01"
                iconType="solar"
                :current="route_is('business.settings.*')"
            text="{{ __('business/labels.account_settings') }}"/>

            <x-sidebar.navlist-item
                href="{{ route('document.help.index') }}"
                iconName="help-01"
                iconType="solar"
                :current="false"
            text="{{ __('business/labels.help') }}"/>

            <x-sidebar.navlist-item
                href="{{ config('business.links.business_account_guide') }}"
                iconName="arrow-up-right"
                iconType="line"
            text="{{ __('business/labels.about_account') }}"/>


        </x-sidebar.navlist>
		<div class="mt-auto">
			<div class="flex flex-wrap gap-1">
                @if(config('features.dark_theme.enabled'))
                    <x-sidebar.link href="{{ url('settings/theme') }}" target="_blank">
                        {{ __('labels.theme') }}
                    </x-sidebar.link>
                @endif

                <x-sidebar.link href="{{ route('document.privacy.index') }}" target="_blank">
                    {{ __('business/labels.privacy_policy') }}
                </x-sidebar.link>
                <x-sidebar.link href="{{ route('document.terms.index') }}" target="_blank">
                    {{ __('business/labels.terms_of_use') }}
                </x-sidebar.link>
                <x-sidebar.link href="{{ url('settings/language') }}" target="_blank">
                    {{ __('business/labels.language') }}
                </x-sidebar.link>

                <x-sidebar.link href="{{ url('/') }}" target="_blank">{{ config('app.name') }} &copy; {{ now()->year }}</x-sidebar.link>

                @unless(config('app.hide_author_attribution'))
                    <x-sidebar.link href="https://www.terla.me" target="_blank">Created by Mansur Terla</x-sidebar.link>
                @endunless
			</div>
		</div>
    </x-sidebar.navbar>
</x-sidebar.container>
