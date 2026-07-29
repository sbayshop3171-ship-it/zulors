@extends('documentLayout::index')

@section('pageContent')
    @includeIf('document::author.i18n.' . app()->getLocale())
@endsection