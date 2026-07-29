<x-header>
    <x-slot:controls>
        @if(route_is('business.ads.*'))
            <x-header-btn link="{{ route('business.ads.create') }}" btnText="{{ __('business/ads.create_title') }}"></x-header-btn>
        @elseif(route_is('business.market.*'))
            <x-header-btn link="{{ route('business.market.create') }}" btnText="{{ __('business/market.create_title') }}"></x-header-btn>
        @elseif(route_is('business.jobs.*'))
            <x-header-btn link="{{ route('business.jobs.create') }}" btnText="{{ __('business/jobs.create_title') }}"></x-header-btn>
        @elseif(route_is('business.services.*'))
            <x-header-btn link="{{ route('business.services.create') }}" btnText="{{ __('business/services.create_title') }}"></x-header-btn>
        @endif
    </x-slot:controls>
</x-header>