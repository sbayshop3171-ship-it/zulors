@extends('authLayout::index')

@section('pageContent')
    <div class="block">
        @livewire('user.auth.forgot-success', ['confirmationData' => $confirmationData])
    </div>
@endsection
