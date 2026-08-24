@php
    $showFooterAppBadges = $showFooterAppBadges ?? true;
@endphp

<footer class="site-footer pb-4 pt-6 flex" style="min-width: 320px;">
    <div class="site-footer__inner app-container mx-auto flex-1 px-4 md:px-8">
        <nav class="site-footer__nav flex flex-wrap justify-center gap-2 md:gap-4 text-center">
            <a href="{{ route('document.about.index') }}" class="text-par-s text-lab-pr2 hover:text-brand-900 smoothing">
                {{ __('links.about_project') }}
            </a>
            <a href="{{ route('document.help.index') }}" class="text-par-s text-lab-pr2 hover:text-brand-900 smoothing">
                {{ __('links.help_center') }}
            </a>
            <a href="{{ route('document.terms.index') }}" class="text-par-s text-lab-pr2 hover:text-brand-900 smoothing">
                {{ __('links.terms_of_use') }}
            </a>
            <a href="{{ route('document.privacy.index') }}" class="text-par-s text-lab-pr2 hover:text-brand-900 smoothing">
                {{ __('links.privacy_policy') }}
            </a>
            <a href="{{ route('document.cookies.index') }}" class="text-par-s text-lab-pr2 hover:text-brand-900 smoothing">
                {{ __('links.cookies_policy') }}
            </a>
            <a href="{{ route('document.child-safety.index') }}" class="text-par-s text-lab-pr2 hover:text-brand-900 smoothing">
                {{ __('links.child_safety_standards') }}
            </a>
            <a href="{{ route('document.developers.index') }}" class="text-par-s text-lab-pr2 hover:text-brand-900 smoothing">
                {{ __('links.developers') }}
            </a>

            @if(config('features.dark_theme.enabled'))
                @if(theme_name() == 'dark')
                    <a href="{{ route('user.theme.switch', ['theme' => 'light']) }}" class="text-par-s text-lab-pr2 hover:text-brand-900 smoothing">
                        {{ __('labels.light_theme') }}
                    </a>
                @else
                    <a href="{{ route('user.theme.switch', ['theme' => 'dark']) }}" class="text-par-s text-lab-pr2 hover:text-brand-900 smoothing">
                        {{ __('labels.dark_theme') }}
                    </a>
                @endif
            @endif
        </nav>
        <div class="site-footer__divider h-px bg-fill-pr my-4"></div>
        <div class="site-footer__meta flex flex-col md:flex-row flex-wrap items-center justify-center gap-4 text-center">
            <span class="site-footer__copyright text-par-s text-lab-pr2 hover:text-brand-900 smoothing">
                Copyright © {{ date('Y') }} {{ config('app.name') }}
            </span>

            @if($showFooterAppBadges && (config('app.mobile_app_ios_link') || config('app.mobile_app_android_link')))
                <div class="site-footer__badges flex md:inline-flex items-center justify-center gap-2">
                    @if(config('app.mobile_app_ios_link'))
                        <a href="{{ config('app.mobile_app_ios_link') }}" class="h-7">
                            <x-get-apps.cards.app-store></x-get-apps.cards.app-store>
                        </a>
                    @endif
                    @if(config('app.mobile_app_android_link'))
                        <a href="{{ config('app.mobile_app_android_link') }}" class="h-7">
                            <x-get-apps.cards.google-play></x-get-apps.cards.google-play>
                        </a>
                    @endif
                </div>
            @endif
        </div>
    </div>
</footer>
