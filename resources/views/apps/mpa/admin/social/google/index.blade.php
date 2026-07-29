@extends('adminLayout::index')

@section('pageContent')
    <x-page-title titleText=" {{ __('admin/social.index_title') }}"></x-page-title>

    <x-sided-content>
        <x-slot:sideContent>
            <x-entity.previews.social-login></x-entity.previews.social-login>
        </x-slot:sideContent>
        <div>
            @livewire('admin.config.google-login')
        </div>
    </x-sided-content>
@endsection
