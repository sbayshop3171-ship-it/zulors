<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name') }}</title>

        @include('layouts.parts.meta')
        @include('layouts.parts.favicons')

        @vite([
            'resources/js/admin/main.js',
            'resources/js/mpa/rich.editor.js',
            config('assets.fonts.sans'),
            config('assets.fonts.mono')
        ])

        @if(theme_name() == 'dark')
            <link rel="stylesheet" href="{{ asset('build/assets/admin-main-dark.css') }}?v={{ $buildNumber }}">
        @else
            @vite('resources/css/admin/main.css')
            @vite('resources/css/mpa/rich.editor.css')
        @endif

        @stack('styles')

        @stack('scripts')

        @livewireStyles
    </head>

    <body @class(['pt-20 pb-6', (theme_name() == 'dark' ? 'bg-black' : 'bg-pr')])>
        <x-main>
            @include('adminLayout::parts.sidebar')

            @include('adminLayout::parts.header')

            <x-container>
                <x-messages.primary></x-messages.primary>
                <div class="app-min-vh">
                    @yield('pageContent')
                </div>

                <div class="fixed bg-bg-pr border-t border-bord-tr bottom-0 right-0 left-80 px-16 h-6 flex items-center">
                    <span class="block text-lab-sc text-cap-s">{{ __('admin/info.you_are_admin')}}</span>
                </div>
            </x-container>

            <x-modals.confirm.confirm></x-modals.confirm.confirm>


            @livewireScripts
        </x-main>
    </body>
</html>
