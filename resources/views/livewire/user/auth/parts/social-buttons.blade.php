@if(count($activeSocialDrivers))
    <div class="flex flex-col gap-3 mb-6">
        @php
            $primaryOptions = ($showAllSocialOptions == true) ? collect($activeSocialDrivers)->all() : collect($activeSocialDrivers)->take(4);
        @endphp

        @foreach($primaryOptions as $driverName => $driver)
            <x-auth.social.button
                href="{{ route($driver['meta']['url']) }}"
                data-native-google-signin="{{ $driverName === 'google' ? 'true' : '' }}"
                data-native-google-driver="{{ $driverName === 'google' ? $driverName : '' }}"
                data-native-google-client-id="{{ $driverName === 'google' ? data_get($driver, 'credentials.client_id', '') : '' }}"
            >
                <x-slot:iconSlot>
                    <img class="w-full" src="{{ asset($driver['meta']['logo']) }}" alt="Logo">
                </x-slot:iconSlot>
                {{ __('auth.login_with', ['provider_name' => $driver['meta']['name'] ]) }}
            </x-auth.social.button>
        @endforeach

        @if (count($activeSocialDrivers) > 4 && empty($showAllSocialOptions))
            <button type="button" class="border border-edge-sc rounded-md w-full cursor-pointer disabled:opacity-60 disabled:cursor-wait" wire:click="showAllSocialLoginOptions" wire:loading.attr="disabled" wire:target="showAllSocialLoginOptions">
                <span class="flex w-full h-14 items-center justify-center gap-1" wire:loading.remove wire:target="showAllSocialLoginOptions">
                    <span class="text-center text-lab-pr2 text-par-m font-medium">
                        {{ __('auth.other_options') }}
                    </span>
                    <span class="size-icon text-lab-pr2">
                        <x-ui-icon name="chevron-down" type="solid"></x-ui-icon>
                    </span>
                </span>
                <span class="hidden w-full h-14 items-center justify-center" wire:loading.flex wire:target="showAllSocialLoginOptions" aria-hidden="true">
                    <span class="inline-block colibri-primary-animation"></span>
                </span>
            </button>
        @endif
    </div>
    <div class="mb-6">
        <x-auth.form.auth-options-div></x-auth.form.auth-options-div>
    </div>

    @once
        @push('scripts')
            <script>
                (function () {
                    const googleSelector = '[data-native-google-signin="true"]';

                    function hasNativeGoogleBridge() {
                        return typeof window !== 'undefined'
                            && window.ZulorsNativeAuth
                            && typeof window.ZulorsNativeAuth.startGoogleSignIn === 'function'
                            && typeof window.ZulorsNativeAuth.isGoogleSignInAvailable === 'function';
                    }

                    function setGoogleBusyState(isBusy) {
                        document.querySelectorAll(googleSelector).forEach(function (button) {
                            if (!(button instanceof HTMLElement)) {
                                return;
                            }

                            if (isBusy) {
                                button.dataset.nativeGoogleBusy = 'true';
                                button.setAttribute('aria-busy', 'true');
                                button.style.pointerEvents = 'none';
                                button.style.opacity = '0.6';
                                return;
                            }

                            delete button.dataset.nativeGoogleBusy;
                            button.removeAttribute('aria-busy');
                            button.style.pointerEvents = '';
                            button.style.opacity = '';
                        });
                    }

                    document.addEventListener('click', function (event) {
                        const target = event.target instanceof Element
                            ? event.target.closest(googleSelector)
                            : null;

                        if (!target || !hasNativeGoogleBridge()) {
                            return;
                        }

                        let available = false;

                        try {
                            available = Boolean(window.ZulorsNativeAuth.isGoogleSignInAvailable());
                        }
                        catch (error) {
                            available = false;
                        }

                        if (!available) {
                            return;
                        }

                        event.preventDefault();

                        if (target.dataset.nativeGoogleBusy === 'true') {
                            return;
                        }

                        setGoogleBusyState(true);

                        const googleClientId = target.dataset.nativeGoogleClientId || '';
                        let started = false;

                        try {
                            if (googleClientId && typeof window.ZulorsNativeAuth.startGoogleSignInWithClientId === 'function') {
                                started = Boolean(window.ZulorsNativeAuth.startGoogleSignInWithClientId(googleClientId));
                            }
                            else {
                                started = Boolean(window.ZulorsNativeAuth.startGoogleSignIn());
                            }
                        }
                        catch (error) {
                            started = false;
                        }

                        if (!started) {
                            setGoogleBusyState(false);
                        }
                    }, true);

                    window.addEventListener('zulors:native-google-auth', function (event) {
                        const state = event && event.detail ? event.detail.state : '';

                        if (state === 'started') {
                            return;
                        }

                        setGoogleBusyState(false);
                    });
                })();
            </script>
        @endpush
    @endonce
@endif
