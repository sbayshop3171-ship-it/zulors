@if(count($activeSocialDrivers))
    <div class="flex flex-col gap-3 mb-6">
        @php
            $primaryOptions = ($showAllSocialOptions == true) ? collect($activeSocialDrivers)->all() : collect($activeSocialDrivers)->take(4);
        @endphp

        @foreach($primaryOptions as $driver)
            <x-auth.social.button href="{{ route($driver['meta']['url']) }}">
                <x-slot:iconSlot>
                    <img class="w-full" src="{{ asset($driver['meta']['logo']) }}" alt="Logo">
                </x-slot:iconSlot>
                {{ __('auth.login_with', ['provider_name' => $driver['meta']['name'] ]) }}
            </x-auth.social.button>
        @endforeach

        @if (count($activeSocialDrivers) > 4 && empty($showAllSocialOptions))
            <button type="button" class="border border-edge-sc rounded-md w-full" wire:click="showAllSocialLoginOptions" wire:loading.attr="disabled">
                <span class="flex w-full h-14 items-center justify-center gap-1">
                    <span class="text-center text-lab-pr2 text-par-m font-medium">
                        {{ __('auth.other_options') }}
                    </span>
                    <span class="size-icon text-lab-pr2">
                        <x-ui-icon name="chevron-down" type="solid"></x-ui-icon>
                    </span>
                </span>
            </button>
        @endif
    </div>
    <div class="mb-6">
        <x-auth.form.auth-options-div></x-auth.form.auth-options-div>
    </div>
@endif
