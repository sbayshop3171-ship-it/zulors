@extends('layouts.spa.apps.desktop.index')

@section('pageContent')
    <div id="zulors-desktop-app">
        @unless(config('app.hide_author_attribution'))
            @include('apps.spa.devnote')
        @endunless
        @include('apps.spa.boot-shell', ['variant' => 'desktop'])
    </div>
@endsection
