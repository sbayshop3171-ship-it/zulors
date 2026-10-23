<x-header>
    <x-slot:controls>
        @if(route_is('business.ads.*') && ! route_is('business.ads.create'))
            <x-header-btn link="{{ route('business.ads.create') }}" btnText="{{ __('business/ads.create_title') }}" data-business-create-action></x-header-btn>
        @elseif(route_is('business.market.*') && ! route_is('business.market.create'))
            <x-header-btn link="{{ route('business.market.create') }}" btnText="{{ __('business/market.create_title') }}" data-business-create-action></x-header-btn>
        @elseif(route_is('business.jobs.*') && ! route_is('business.jobs.create'))
            <x-header-btn link="{{ route('business.jobs.create') }}" btnText="{{ __('business/jobs.create_title') }}" data-business-create-action></x-header-btn>
        @elseif(route_is('business.services.*'))
            <x-header-btn link="{{ route('business.services.create') }}" btnText="{{ __('business/services.create_title') }}" data-business-create-action></x-header-btn>
        @endif
    </x-slot:controls>
</x-header>
