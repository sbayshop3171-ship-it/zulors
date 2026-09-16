@extends('businessLayout::index')

@section('pageContent')
    <x-page-title titleText="{{ __('business/ads.create_title') }}"></x-page-title>

    <x-content width="w-full max-w-[1280px]">
        @livewire('business.ads.upsert', [
            'adData' => $adData,
            'upsertType' => 'create'
        ])
    </x-content>
@endsection
