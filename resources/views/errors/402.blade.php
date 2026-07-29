@extends('documentLayout::index')

@section('pageContent')
	<x-http.http-error code="402" title="{{ __('errors.http.402.title') }}" message="{{ __('errors.http.402.message') }}" hasBackButton="true"></x-http.http-error>
@endsection