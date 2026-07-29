@extends('adminLayout::index')

@section('pageContent')
    <x-page-title titleText=" {{ __('admin/config.auth_settings') }}"></x-page-title>

    <x-content width="w-full">
		@livewire('admin.config.auth')
	</x-content>
@endsection
