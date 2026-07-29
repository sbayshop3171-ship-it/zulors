@props([
	'controls' => null
])

<header class="mb-6 fixed top-0 right-0 left-72 z-50 bg-bg-pr border-b border-bord-tr">
    <div class="flex items-center py-3 h-16">
		@if(isset($controls))
			<div class="pr-8 ml-auto">
				<div class="flex items-center gap-3 leading-none">
					{{ $controls }}

                    <x-ui.buttons.header
                        url="{{ me()->profile_url }}"
                        avatarUrl="{{ me()->avatar_url }}"/>

                    @if(config('features.dark_theme.enabled'))
                        <x-ui.buttons.header
                            url="{{ route('user.theme.switch', theme_name() == 'dark' ? 'light' : 'dark') }}"
                            iconName="{{ theme_name() == 'dark' ? 'sun-01' : 'moon-01' }}"
                        iconType="solar" />
                    @endif
				</div>
			</div>
		@endif
    </div>
</header>
