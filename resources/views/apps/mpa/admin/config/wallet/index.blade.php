@extends('adminLayout::index')

@section('pageContent')
    <x-page-title titleText=" {{ __('admin/wallet.settings') }}"></x-page-title>

    <x-sided-content>
        <x-slot:sideContent>
            <x-entity.previews.wallet></x-entity.previews.wallet>
        </x-slot:sideContent>
        <div>
            @livewire('admin.config.wallet')
        </div>
    </x-sided-content>
@endsection
