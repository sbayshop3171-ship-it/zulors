@extends('businessLayout::index')

@section('pageContent')
    <x-page-title titleText="{{ __('business/jobs.edit_title') }}"></x-page-title>
	<x-content>
		@livewire('business.jobs.upsert', [
			'jobData' => $jobData,
			'upsertType' => 'edit'
		])
	</x-content>
@endsection
