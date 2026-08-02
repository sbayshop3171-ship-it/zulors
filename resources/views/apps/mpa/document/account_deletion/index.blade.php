@extends('documentLayout::index')

@section('pageContent')
    @includeIf('document::account_deletion.i18n.' . app()->getLocale())
@endsection
