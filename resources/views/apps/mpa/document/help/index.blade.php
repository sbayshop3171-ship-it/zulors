@extends('documentLayout::index')

@section('pageContent')
    @includeIf('document::help.i18n.' . app()->getLocale())
@endsection