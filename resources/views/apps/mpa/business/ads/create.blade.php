@extends('businessLayout::index')

@section('pageContent')
    <x-page-title titleText="{{ __('business/ads.create_title') }}"></x-page-title>

    <x-content>
        @livewire('business.ads.upsert', [
            'adData' => $adData,
            'upsertType' => 'create'
        ])
    </x-content>
@endsection
