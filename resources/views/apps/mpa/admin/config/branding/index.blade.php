@extends('adminLayout::index')

@section('pageContent')
    <x-page-title titleText=" {{ __('admin/brand.index_title') }}"></x-page-title>

    <x-content width="w-full">
        @livewire('admin.config.branding')
    </x-content>
@endsection
