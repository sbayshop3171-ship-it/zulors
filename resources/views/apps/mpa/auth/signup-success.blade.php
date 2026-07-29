@extends('authLayout::index')

@section('pageContent')
    <div class="block">
        @livewire('user.auth.signup-success', ['confirmationData' => $confirmationData])
    </div>
@endsection
