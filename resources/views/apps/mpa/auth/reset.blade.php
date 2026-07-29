@extends('authLayout::index')

@section('pageContent')
    <div class="block">
        @livewire('user.auth.reset', ['confirmationData' => $confirmationData])
    </div>
@endsection
