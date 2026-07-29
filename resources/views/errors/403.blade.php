@extends('documentLayout::index')

@section('pageContent')
	<x-http.http-error code="403" title="{{ __('errors.http.403.title') }}" message="{{ __('errors.http.403.message') }}" hasBackButton="true"></x-http.http-error>
@endsection