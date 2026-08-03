@extends('documentLayout::index')

@section('pageContent')
    @includeIf('document::child_safety.i18n.' . app()->getLocale())
@endsection
