@extends('businessLayout::index')

@section('pageContent')
    <div class="w-content border border-bord-sc rounded-2xl">
        <div class="rounded-2xl overflow-hidden">
            <div class="bg-lab-pr text-bg-pr p-6">
                <div class="mb-2">
                    <div class="size-large-avatar border-bg-sc/30 border-4 rounded-full overflow-hidden">
                        <x-general.avatars.img src="{{ me()->avatar_url }}" class="w-full" alt="Avatar" />
                    </div>
                </div>
                <h4 class="text-title-3 tracking-tighter font-medium">
                    {{ __('labels.hi_user', ['name' => me()->first_name]) }}
                </h4>

                <p class="opacity-90 text-par-n">
                    {{ __('business/dashboard.welcome_title') }}
                </p>
            </div>
            <div class="bg-bg-pr p-6">
                <p class="text-par-n mb-3 text-lab-pr2">
                    {!! __('business/dashboard.about_business_account') !!}
                </p>
                <a href="{{ config('business.links.business_account_guide') }}" class="text-par-s hover:underline text-brand-900">
                    {{ __('labels.learn_more') }} &raquo;
                </a>
            </div>
        </div>
        <div class="bg-bg-pr rounded-2xl p-6 mt-4 hover:border-brand-900 cursor-pointer smoothing">
            <a href="{{ route('document.help.index') }}" class="w-full flex items-center">
                <div class="flex-1 pr-6">
                    <h4 class="text-par-l tracking-tighter text-brand-900 font-medium mb-1">
                        {{ __('business/dashboard.offer_help.title') }}
                    </h4>
                    <p class="text-par-s text-lab-sc">
                        {{ __('business/dashboard.offer_help.desc') }}
                    </p>
                </div>
                <div class="shrink-0 size-6 text-lab-tr">
                    <x-ui-icon name="help-circle" type="line"></x-ui-icon>
                </div>
            </a>
        </div>
    </div>

    <div class="mt-3">
        <span class="text-cap-l italic text-lab-sc">
            {{ __('business/dashboard.features_restriction_info') }}
        </span>
    </div>
@endsection
