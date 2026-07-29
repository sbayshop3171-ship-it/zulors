
@extends('adminLayout::index')

@section('pageContent')
	<x-page-title titleText=" {{ $upsertType == 'create' ? __('admin/categories.create_title') : __('admin/categories.edit_title') }}"></x-page-title>

	<x-content>
		@include('admin::categories.upsert.parts.form')
	</x-content>
@endsection
