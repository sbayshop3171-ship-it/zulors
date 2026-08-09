<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ theme_name() }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
        <meta name="theme-color" content="{{ theme_name() == 'dark' ? '#111111' : '#ffffff' }}">
        <meta name="color-scheme" content="{{ theme_name() == 'dark' ? 'dark light' : 'light dark' }}">

        <title>{{ config('app.name') }}</title>

        @include('layouts.parts.meta')
        @include('layouts.parts.favicons')
        @include('layouts.spa.apps.parts.boot-shell-styles')
        @include('layouts.spa.apps.parts.boot-shell-hints', ['variant' => 'desktop'])

        @vite([
            'resources/js/spa/apps/desktop/bootstrap/application.js',
            config('assets.fonts.sans'),
            config('assets.fonts.mono')
        ])

        @if(theme_name() == 'dark')
            @vite('resources/css/spa/apps/desktop/dark/main.css')
        @else
            @vite('resources/css/spa/apps/desktop/main.css')
        @endif

        @include('layouts.spa.apps.parts.pwa')

        @include('layouts.parts.head-code')
    </head>
    <body class="font-sans antialiased bg-bg-pr min-w-[1200px]" data-theme="{{ theme_name() }}">
        <x-device-switcher.desktop></x-device-switcher.desktop>

        @yield('pageContent')

        @include('layouts.spa.apps.parts.embeds')

        @include('layouts.parts.footer-code')
    </body>
</html>
