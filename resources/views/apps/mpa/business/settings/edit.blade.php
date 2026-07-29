@extends('businessLayout::index')

@section('pageContent')
    <x-page-title titleText="{{ __('business/settings.edit_title') }}"></x-page-title>
	<x-content>
		@livewire('business.account.edit')
	</x-content>
@endsection
