@extends('businessLayout::index')

@section('pageContent')
    <x-page-title titleText="{{ __('business/market.edit_title') }}"></x-page-title>
    <x-content>
        @livewire('business.market.upsert', [
            'productData' => $productData,
            'upsertType' => 'edit'
        ])
    </x-content>
@endsection
