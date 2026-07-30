<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ theme_name() }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="{{ theme_name() == 'dark' ? '#111111' : '#ffffff' }}">
        <meta name="color-scheme" content="{{ theme_name() == 'dark' ? 'dark light' : 'light dark' }}">

        <title>{{ config('app.name') }}</title>

        @include('layouts.parts.meta')
        @include('layouts.parts.favicons')

        @vite([
            'resources/js/business/main.js',
            config('assets.fonts.sans'),
            config('assets.fonts.mono')
        ])

        @if(theme_name() == 'dark')
            <link rel="stylesheet" href="{{ asset('build/assets/business-main-dark.css') }}?v={{ $buildNumber }}">
        @else
            @vite('resources/css/business/main.css')
        @endif

        @stack('styles')

        @stack('scripts')

        @livewireStyles
    </head>

    @php
        $businessAutoRefresh = request()->routeIs(
            'business.dashboard.index',
            'business.market.index',
            'business.jobs.index',
            'business.ads.index',
            'business.wallet.index',
            'business.wallet.cashouts',
        );
    @endphp

    <body
        @class(['business-app pt-20', (theme_name() == 'dark' ? 'bg-black' : 'bg-pr')])
        data-theme="{{ theme_name() }}"
        data-business-auto-refresh="{{ $businessAutoRefresh ? 'on' : 'off' }}"
    >
        <x-main>
            @include('businessLayout::parts.sidebar')

            @include('businessLayout::parts.header')

            @include('businessLayout::parts.mobile-nav')

            <div class="business-content-shell" data-business-refresh-region>
                <x-messages.primary></x-messages.primary>

                <div class="app-min-vh">
                    @yield('pageContent')
                </div>
            </div>

            <x-modals.confirm.confirm></x-modals.confirm.confirm>

            @livewireScripts
        </x-main>
    </body>
</html>
