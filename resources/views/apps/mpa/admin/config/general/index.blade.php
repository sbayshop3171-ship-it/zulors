@extends('adminLayout::index')

@section('pageContent')
    <x-page-title titleText=" {{ __('admin/config.general_settings') }}"></x-page-title>

	<x-content>
        @livewire('admin.config.general')
	</x-content>
@endsection
