@extends('emails.layouts.main')

@section('email_content')
    <x-emails.title>
        {{ __('admin/notifications.user_banned.title') }}
    </x-emails.title>

    <x-emails.par>
        {{ __('admin/notifications.user_banned.line_one', ['user_name' => $userData->name, 'app_name' => config('app.name')]) }}
    </x-emails.par>

    <x-emails.spacer space="24"></x-emails.spacer>

    <x-emails.spacer space="32"></x-emails.spacer>
@endsection