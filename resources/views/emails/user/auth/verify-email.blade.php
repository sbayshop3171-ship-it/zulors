@extends('emails.layouts.main')

@section('email_content')
    <x-emails.title>
        {{ $data['title'] }}
    </x-emails.title>

    <x-emails.spacer space="24"></x-emails.spacer>
    <x-emails.par>
        We’re excited to have you on board. Before you get started, enter this 4-digit code to verify your email address.
    </x-emails.par>
    <x-emails.spacer space="32"></x-emails.spacer>

    <x-emails.par>
        <b>
            Your verification code:
        </b>
    </x-emails.par>
    <x-emails.par>
        <x-emails.code>{{ $data['code'] ?? '' }}</x-emails.code>
    </x-emails.par>
    <x-emails.spacer space="12"></x-emails.spacer>
    <x-emails.par>
        This code expires in 10 minutes.
    </x-emails.par>
    <x-emails.spacer space="32"></x-emails.spacer>
    <x-emails.par>
        If you didn’t create an account with {{ config('app.name') }}, please disregard this email.
    </x-emails.par>
    <x-emails.spacer space="32"></x-emails.spacer>
    <x-emails.par>
        Thank you for joining us!
    </x-emails.par>
    <x-emails.spacer space="12"></x-emails.spacer>
    <x-emails.par>
        {{ __('email.regards') }},
        {{ __('email.team_caption', ['app_name' => config('app.name')]) }}
    </x-emails.par>
@endsection
