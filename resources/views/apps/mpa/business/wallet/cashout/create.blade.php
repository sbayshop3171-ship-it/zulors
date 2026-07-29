@extends('businessLayout::index')

@section('pageContent')
    <x-page-title titleText="{{ __('business/wallet.withdrawal_title') }}"></x-page-title>

    <x-content>
        @livewire('business.wallet.request')
    </x-content>
@endsection
