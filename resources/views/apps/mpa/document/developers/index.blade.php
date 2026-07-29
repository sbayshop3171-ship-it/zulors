@extends('documentLayout::index')

@section('pageContent')
    @includeIf('document::developers.i18n.' . app()->getLocale())
@endsection