@extends('documentLayout::index')

@section('pageContent')
    @includeIf('document::about.i18n.' . app()->getLocale())
@endsection