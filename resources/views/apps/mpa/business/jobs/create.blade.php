@extends('businessLayout::index')

@section('pageContent')
    <x-page-title titleText="{{ __('business/jobs.create_title') }}"></x-page-title>

    <x-content>
        @livewire('business.jobs.upsert', [
            'jobData' => $jobData,
            'upsertType' => 'create'
        ])
    </x-content>
@endsection
