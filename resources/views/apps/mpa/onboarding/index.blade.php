@extends('authLayout::index')

@section('pageContent')
    <div>
        <div class="mb-16">
            <x-auth.parts.form-header title="{{ __('labels.signup_almost_done.title') }}">
                <x-slot:icon>
                    <x-ui-icon name="user-02" type="line"></x-ui-icon>
                </x-slot:icon>
                <x-slot:caption>
                    {{ __('labels.signup_almost_done.caption') }}
                </x-slot:caption>
            </x-auth.parts.form-header>
        </div>
        <div class="mb-6">
            <span class="text-lab-sc text-par-m mb-2 inline-block">
                {{ __('labels.signup_steps', ['current' => ($stepIndex + 1), 'total' => $totalSteps]) }}
            </span>
            <div class="h-0.5 bg-fill-tr leading-none">
                <div class="h-0.5 bg-brand-900 min-w-4" style="width: {{ round((($stepIndex + 1) / $totalSteps) * 100)}}%;"></div>
            </div>
        </div>
        
        @livewire($stepComponentData['component'], [
            'stepIndex' => $stepIndex,
            'stepData' => $stepComponentData,
        ])

        <div>
            <p class="text-par-s text-lab-sc text-center">
                {!! __('auth.auth_agreement', [
                    'app_name' => config('app.name'),
                    'terms_link' => route('document.terms.index'),
                    'policy_link' => route('document.privacy.index')
                ]) !!}
            </p>
        </div>
    </div>
@endsection