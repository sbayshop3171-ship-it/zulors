<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name') }}</title>

        @include('layouts.parts.meta')
        @include('layouts.parts.favicons')

        @vite([
            'resources/js/document/main.js',
            'resources/css/document/main.css',
            config('assets.fonts.sans'),
            config('assets.fonts.mono')
        ])

        @stack('styles')

        @stack('scripts')

        @include('layouts.parts.head-code')
    </head>
    <body class="bg-bg-pr pt-14">
        @include('layouts.mpa.parts.header')

        <div class="flex-col flex min-h-screen">
            <div class="flex justify-center py-12 flex-1">
                <div class="max-w-3xl px-4 md:px-8 document-container">
                    @yield('pageContent')
                </div>
            </div>
            @include('layouts.mpa.parts.footer')
        </div>

        @include('layouts.parts.footer-code')
    </body>
</html>
