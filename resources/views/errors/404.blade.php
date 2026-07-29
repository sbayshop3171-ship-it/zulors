@extends('documentLayout::index')

@section('pageContent')
	<x-http.http-error code="404" title="{{ __('errors.http.404.title') }}" message="{{ __('errors.http.404.message') }}" hasBackButton="true"></x-http.http-error>
@endsection