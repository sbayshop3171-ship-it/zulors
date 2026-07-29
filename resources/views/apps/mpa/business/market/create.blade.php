@extends('businessLayout::index')

@section('pageContent')
    <x-page-title titleText="{{ __('business/market.create_title') }}"></x-page-title>
    <x-content>
        @livewire('business.market.upsert', [
            'productData' => $productData,
            'upsertType' => 'create'
        ])
    </x-content>
@endsection
