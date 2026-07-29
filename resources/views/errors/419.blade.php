@extends('documentLayout::index')

@section('pageContent')
	<x-http.http-error code="419" title="{{ __('errors.http.419.title') }}" message="{{ __('errors.http.419.message') }}" hasBackButton="true"></x-http.http-error>
@endsection