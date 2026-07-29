@extends('documentLayout::index')

@section('pageContent')
	<x-http.http-error code="500" title="{{ __('errors.http.500.title') }}" message="{{ __('errors.http.500.message') }}" hasBackButton="true"></x-http.http-error>
@endsection
