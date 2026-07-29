@extends('documentLayout::index')

@section('pageContent')
    @includeIf('document::terms.i18n.' . app()->getLocale())
@endsection