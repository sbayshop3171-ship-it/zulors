@extends('documentLayout::index')

@section('pageContent')
    @includeIf('document::cookies.i18n.' . app()->getLocale())
@endsection