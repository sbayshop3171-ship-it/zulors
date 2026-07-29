<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">

        <title>{{ config('app.name') }}</title>

        @include('layouts.parts.meta')
        @include('layouts.parts.favicons')

        @vite([
            config('assets.fonts.sans'),
            config('assets.fonts.mono')
        ])

        @php
            $desktopAuthDarkStylesheet = public_path('build/assets/desktop-auth-dark.css');
        @endphp

        @if(theme_name() == 'dark' && file_exists($desktopAuthDarkStylesheet))
            <link rel="stylesheet" href="{{ asset('build/assets/desktop-auth-dark.css') }}?v={{ $buildNumber }}">
        @else
            @vite('resources/css/spa/apps/desktop/auth.css')
        @endif

        @livewireStyles
        @stack('styles')

        @include('layouts.parts.head-code')
    </head>
    <body class="bg-bg-pr pt-14" style="min-width: 320px;">
        @include('layouts.mpa.parts.header')

        <div class="flex-col flex min-h-screen">
            <div class="flex justify-center py-12 md:py-24 px-4 flex-1">
                <div class="auth-content">
                    @yield('pageContent')
                </div>
            </div>

            @if(config('app.mobile_app_ios_link') || config('app.mobile_app_android_link'))
                <div class="flex justify-center px-4 pb-8">
                    <div class="inline-flex items-center gap-3 rounded-md border border-fill-pr bg-bg-pr px-5 py-4">
                        @if(config('app.mobile_app_ios_link'))
                            <a href="{{ config('app.mobile_app_ios_link') }}" class="h-9">
                                <x-get-apps.cards.app-store></x-get-apps.cards.app-store>
                            </a>
                        @endif
                        @if(config('app.mobile_app_android_link'))
                            <a href="{{ config('app.mobile_app_android_link') }}" class="h-9">
                                <x-get-apps.cards.google-play></x-get-apps.cards.google-play>
                            </a>
                        @endif
                    </div>
                </div>
            @endif

            @include('layouts.mpa.parts.footer', ['showFooterAppBadges' => false])
        </div>

        @stack('scripts')
        @livewireScripts

        @include('layouts.parts.footer-code')
    </body>
</html>
