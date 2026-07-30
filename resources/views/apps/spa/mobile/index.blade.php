@extends('layouts.spa.apps.mobile.index')

@section('pageContent')
    <div id="zulors-mobile-app">
        @include('apps.spa.devnote')
        @include('apps.spa.boot-shell', ['variant' => 'mobile'])
    </div>
@endsection
