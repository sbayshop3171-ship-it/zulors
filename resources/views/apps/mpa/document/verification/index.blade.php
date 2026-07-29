@extends('documentLayout::index')

@section('pageContent')
    @includeIf('document::verification.i18n.' . app()->getLocale())
@endsection