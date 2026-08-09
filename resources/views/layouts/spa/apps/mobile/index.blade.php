<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ theme_name() }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
        <meta name="theme-color" content="{{ theme_name() == 'dark' ? '#111111' : '#ffffff' }}">
        <meta name="color-scheme" content="{{ theme_name() == 'dark' ? 'dark light' : 'light dark' }}">

        <title>{{ config('app.name') }}</title>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Grand+Hotel&display=swap">

        @include('layouts.parts.meta')
        @include('layouts.parts.favicons')
        @include('layouts.spa.apps.parts.boot-shell-styles')
        @include('layouts.spa.apps.parts.boot-shell-hints', ['variant' => 'mobile'])

        @vite([
            'resources/js/spa/apps/mobile/bootstrap/application.js',
            config('assets.fonts.sans'),
            config('assets.fonts.mono')
        ])

        @if(theme_name() == 'dark')
            @vite('resources/css/spa/apps/mobile/dark/main.css')
        @else
            @vite('resources/css/spa/apps/mobile/main.css')
        @endif

        @include('layouts.spa.apps.parts.pwa')

        @include('layouts.parts.head-code')
    </head>
    <body data-theme="{{ theme_name() }}">
        <x-device-switcher.mobile></x-device-switcher.mobile>

        @yield('pageContent')

        @include('layouts.spa.apps.parts.embeds')

        @include('layouts.parts.footer-code')
    </body>
</html>
