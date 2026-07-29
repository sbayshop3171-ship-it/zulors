@extends('adminLayout::index')

@section('pageContent')
    <x-page-title titleText=" {{ __('admin/acquiring.index_title') }}"></x-page-title>

    <div class="grid grid-cols-1 gap-8">
        <div class="col-span-1">
            <x-navbar title="{{ __('admin/config.tabs.acquiring_providers') }}">
                @foreach($acquiringProviders as $provider)
                    <x-navbar.item
                        href="{{ route('admin.acquiring.edit', ['provider' => $provider['driver']]) }}"
                        iconSize="size-12"
                    iconUrl="{{ asset($provider['logo']) }}">

                        {{ $provider['name'] }}
                    </x-navbar.item>
                @endforeach
            </x-navbar>
        </div>
    </div>
@endsection
