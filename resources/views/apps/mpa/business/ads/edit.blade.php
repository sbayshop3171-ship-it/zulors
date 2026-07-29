@extends('businessLayout::index')

@section('pageContent')
    <x-page-title titleText="{{ __('business/ads.edit_title') }}"></x-page-title>

    <x-content>
        @livewire('business.ads.upsert', [
            'adData' => $adData,
            'upsertType' => 'edit'
        ])
    </x-content>
@endsection
