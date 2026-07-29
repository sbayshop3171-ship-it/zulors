@extends('adminLayout::index')

@section('pageContent')
    <x-page-title titleText=" {{ __('admin/config.backup_settings') }}"></x-page-title>

    @livewire('admin.config.backups')
@endsection
