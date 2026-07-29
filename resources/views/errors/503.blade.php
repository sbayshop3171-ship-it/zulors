@extends('documentLayout::index')

@section('pageContent')
	<x-http.http-error code="503" title="{{ __('errors.http.503.title') }}" message="{{ __('errors.http.503.message') }}" hasBackButton="true"></x-http.http-error>
@endsection
