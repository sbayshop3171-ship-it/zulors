@extends('adminLayout::index')

@section('pageContent')
    <x-page-title titleText=" {{ __('admin/acquiring.edit_title') }}"></x-page-title>

    <x-sided-content>
        <x-slot:sideContent>
            <x-config.acquiring></x-config.acquiring>
        </x-slot:sideContent>

        @if($provider == 'paypal')
            @livewire('admin.config.paypal')
        @elseif($provider == 'stripe')
            @livewire('admin.config.stripe')
        @elseif(in_array($provider, ['yoo_kassa', 'yookassa']))
            @livewire('admin.config.yookassa')
        @elseif($provider == 'robokassa')
            @livewire('admin.config.robokassa')
        @endif
    </x-sided-content>
@endsection
