<div class="flex flex-col gap-5 mb-4">
	{{ $slot }}

    <x-sidebar.navlist-div/>

    <x-sidebar.navlist-item
        href="{{ route('user.auth.logout') }}"
        iconName="logout-01"
        iconType="solar"
    text="{{ __('labels.logout') }}"/>
</div>
