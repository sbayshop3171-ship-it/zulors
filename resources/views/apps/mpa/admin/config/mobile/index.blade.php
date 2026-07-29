@extends('adminLayout::index')

@section('pageContent')
    <x-page-title titleText=" {{ __('admin/mobile.index_title') }}"></x-page-title>

    <div class="mb-4">
		<x-tabs.tabs>
			<x-tabs.tab-item :active="$tab == 'mobile'" href="{{ route('admin.config.mobile-apps') }}" textLabel="{{ __('admin/sidebar.mobile_app_settings') }}"></x-tabs.tab-item>
			<x-tabs.tab-item :active="$tab == 'pwa'" href="{{ route('admin.config.mobile-apps', ['tab' => 'pwa']) }}" textLabel="{{ __('admin/sidebar.pwa_settings') }}"></x-tabs.tab-item>
		</x-tabs.tabs>
	</div>

	<x-content>
        @if ($tab == 'mobile')
            @livewire('admin.config.mobile')
        @elseif ($tab == 'pwa')
            @livewire('admin.config.pwa')
        @endif
	</x-content>
@endsection
