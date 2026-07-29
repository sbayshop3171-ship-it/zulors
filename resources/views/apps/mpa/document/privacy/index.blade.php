@extends('documentLayout::index')

@section('pageContent')
    @includeIf('document::privacy.i18n.' . app()->getLocale())
@endsection