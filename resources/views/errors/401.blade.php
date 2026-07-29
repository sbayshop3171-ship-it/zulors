@extends('documentLayout::index')

@section('pageContent')
	<x-http.http-error code="401" title="{{ __('errors.http.401.title') }}" message="{{ __('errors.http.401.message') }}" hasBackButton="true"></x-http.http-error>
@endsection