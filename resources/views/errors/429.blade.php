@extends('documentLayout::index')

@section('pageContent')
	<x-http.http-error code="429" title="{{ __('errors.http.429.title') }}" message="{{ __('errors.http.429.message') }}" hasBackButton="true"></x-http.http-error>
@endsection