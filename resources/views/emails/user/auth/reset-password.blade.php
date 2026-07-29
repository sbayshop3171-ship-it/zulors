@extends('emails.layouts.main')

@section('email_content')
    <x-emails.title>
        {{ __('email.greeting', ['user_name' => $data['name']]) }}
    </x-emails.title>

    <x-emails.spacer space="24"></x-emails.spacer>
    <x-emails.par>
        {{ __('emails/reset.emailing_reason') }}
    </x-emails.par>
    <x-emails.spacer space="32"></x-emails.spacer>

    <x-emails.par>
        <b>
            {{ __('emails/reset.follow_link') }}
        </b>
        <x-emails.link link="{{ $data['link'] }}"></x-emails.link>
    </x-emails.par>
    <x-emails.spacer space="32"></x-emails.spacer>
    <x-emails.par>
        {{ __('emails/reset.ignore_email') }}
    </x-emails.par>
    <x-emails.spacer space="32"></x-emails.spacer>
    <x-emails.spacer space="12"></x-emails.spacer>
    <x-emails.par>
        {{ __('email.regards') }},
        {{ __('email.team_caption', ['app_name' => config('app.name')]) }}
    </x-emails.par>
@endsection