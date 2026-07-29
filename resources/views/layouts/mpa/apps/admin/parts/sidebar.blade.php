<x-sidebar.container>
    <x-sidebar.navbar>
        <div class="mb-12">
            <a href="{{ route('admin.dash.index') }}" class="flex items-center gap-2">
                <img class="h-5" src="{{ $logotypeUrl }}" alt="Image">
                <span class="font-bold text-lab-pr">
                    {{ __('admin/labels.admin_panel') }}
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
                href="{{ route('admin.dash.index') }}"
                iconName="dash-02"
                iconType="solar"
                :current="route_is('admin.dash.*')"
            text="{{ __('admin/sidebar.dashboard') }}"/>

            <x-sidebar.navlist-item
                href="{{ route('admin.lab.index') }}"
                iconName="lab-01"
                iconType="solar"
                :current="route_is('admin.lab.*')"
            text="{{ __('admin/sidebar.lab_tools') }}"/>

            <x-sidebar.navlist-item
                href="{{ route('admin.users.index') }}"
                iconName="users-01"
                iconType="solar"
                :current="route_is('admin.users.*')"
            text="{{ __('admin/sidebar.users') }}"/>

            <x-sidebar.navlist-div/>

            <x-sidebar.navlist-item
                href="{{ route('admin.config.index') }}"
                iconName="settings-01"
                iconType="solar"
                :current="route_is('admin.config.*') || route_is('admin.categories.*') || route_is('admin.lang.*') || route_is('admin.currency.*') || route_is('admin.pages.*')"
            text="{{ __('admin/sidebar.settings') }}"/>

            <x-sidebar.navlist-div/>

            <x-sidebar.navlist-item
                href="{{ route('admin.posts.index') }}"
                iconName="video-lib"
                iconType="solar"
                :current="route_is('admin.posts.*')"
            text="{{ __('admin/sidebar.publications') }}"/>

            <x-sidebar.navlist-item
                href="{{ route('admin.ads.index') }}"
                iconName="chart-01"
                iconType="solar"
                :current="route_is('admin.ads.*')"
            text="{{ __('admin/sidebar.ads_manager') }}"/>

            <x-sidebar.navlist-item
                href="{{ route('admin.stories.index') }}"
                iconName="story-01"
                iconType="solar"
                :current="route_is('admin.stories.*')"
            text="{{ __('admin/sidebar.stories') }}"/>

            <x-sidebar.navlist-item
                href="{{ route('admin.market.index') }}"
                iconName="bag-01"
                iconType="solar"
                :current="route_is('admin.market.*')"
            text="{{ __('admin/sidebar.marketplace') }}"/>

            <x-sidebar.navlist-item
                href="{{ route('admin.jobs.index') }}"
                iconName="case-01"
                iconType="solar"
                :current="route_is('admin.jobs.*')"
            text="{{ __('admin/sidebar.jobs') }}"/>
            <x-sidebar.navlist-item
                href="{{ route('admin.chats.index') }}"
                iconName="chat-01"
                iconType="solar"
                :current="route_is('admin.chats.*')"
            text="{{ __('admin/sidebar.chats_groups') }}"/>

            <x-sidebar.navlist-div/>

            <x-sidebar.navlist-item
                href="{{ route('admin.payments.index') }}"
                iconName="banknote-01"
                iconType="solar"
                :current="route_is('admin.payments.*')"
            text="{{ __('admin/sidebar.payments') }}"/>

            <x-sidebar.navlist-item
                href="{{ route('admin.acquiring.index') }}"
                iconName="wallet-01"
                iconType="solar"
                :current="route_is('admin.acquiring.*')"
            text="{{ __('admin/sidebar.acquiring_settings') }}"/>

            <x-sidebar.navlist-div/>

            <x-sidebar.navlist-item
                href="{{ route('admin.reports.index') }}"
                iconName="flag-01"
                iconType="solar"
                :current="route_is('admin.reports.*')"
            text="{{ __('admin/sidebar.reported_content') }}"/>

            <x-sidebar.navlist-div/>

            <x-sidebar.navlist-item
                href="{{ route('admin.config.verification') }}"
                iconName="verif-01"
                iconType="solar"
                :current="route_is('admin.config.verification')"
            text="{{ __('admin/sidebar.verification') }}"/>

            <x-sidebar.navlist-item
                href="{{ route('admin.authorship.index') }}"
                iconName="crown-01"
                iconType="solar"
                :current="route_is('admin.authorship.*')"
            text="{{ __('admin/sidebar.authorship') }}"/>

            <x-sidebar.navlist-item
                href="{{ route('admin.cashouts.index') }}"
                iconName="money-send-01"
                iconType="solar"
                :current="route_is('admin.cashouts.*')"
            text="{{ __('admin/sidebar.withdrawals') }}"/>

            <x-sidebar.navlist-div/>

            <x-sidebar.navlist-item
                href="{{ route('admin.banning.index') }}"
                iconName="user-block-01"
                iconType="solar"
                :current="route_is('admin.banning.*')"
            text="{{ __('admin/sidebar.banned') }}"/>

            <x-sidebar.navlist-div/>

            <x-sidebar.navlist-item
                href="{{ url(config('log-viewer.route_path')) }}"
                iconName="alert-01"
                iconType="solar"
            text="{{ __('admin/sidebar.logging') }}"/>

            <x-sidebar.navlist-item
                href="{{ config('app.documentation_url') }}"
                iconName="doc-01"
                iconType="solar"
                target="_blank"
            text="{{ __('admin/sidebar.documentation') }}"/>
        </x-sidebar.navlist>
		<div class="mt-auto py-6">
			<div class="flex flex-wrap gap-1">
                @if(config('features.dark_theme.enabled'))
                    <x-sidebar.link href="{{ url('settings/theme') }}" target="_blank">
                        {{ __('labels.theme') }}
                    </x-sidebar.link>
                @endif

                <x-sidebar.link href="{{ route('document.help.index') }}" target="_blank">
                    {{ __('business/labels.help') }}
                </x-sidebar.link>

                <x-sidebar.link href="{{ route('document.developers.index') }}" target="_blank">
                    {{ __('business/labels.for_developers') }}
                </x-sidebar.link>
                <x-sidebar.link href="{{ route('document.privacy.index') }}" target="_blank">
                    {{ __('business/labels.privacy_policy') }}
                </x-sidebar.link>
                <x-sidebar.link href="{{ route('document.terms.index') }}" target="_blank">
                    {{ __('business/labels.terms_of_use') }}
                </x-sidebar.link>
                <x-sidebar.link href="{{ url('settings/language') }}" target="_blank">
                    {{ __('business/labels.language') }}
                </x-sidebar.link>

                <x-sidebar.link href="{{ url('/') }}" target="_blank">
                    {{ config('app.name') }} &copy; {{ now()->year }}  Version #{{ $appVersion }}
                </x-sidebar.link>
                <x-sidebar.link href="{{ route('document.cookies.index') }}" target="_blank">
                    {{ __('links.cookies_policy') }}
                </x-sidebar.link>


                @unless(config('app.hide_author_attribution'))
                    <x-sidebar.link href="https://www.terla.me" target="_blank">Created by Mansur Terla</x-sidebar.link>
                @endunless
			</div>
		</div>
    </x-sidebar.navbar>
</x-sidebar.container>
