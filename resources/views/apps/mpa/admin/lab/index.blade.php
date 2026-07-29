@extends('adminLayout::index')

@section('pageContent')
    <x-page-title titleText=" {{ __('admin/lab.index_title', ['app_name' => config('app.name')]) }}"></x-page-title>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
		@foreach (config('tools') as $toolData)
			@include('admin::lab.parts.tool-item', [
				'toolData' => $toolData
			])
		@endforeach
	</div>

	<x-info.laravel-notice></x-info.laravel-notice>
@endsection
