@extends('adminLayout::index')

@section('pageContent')
    <x-page-title titleText=" {{ __('admin/config.custom_code_settings') }}"></x-page-title>

    <x-content width="w-full">
		@livewire('admin.config.code-injection')
	</x-content>
@endsection
