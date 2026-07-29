@extends('adminLayout::index')

@section('pageContent')
    <x-page-title titleText=" {{ __('admin/config.email_settings') }}"></x-page-title>

	<div class="mb-4">
		<x-tabs.tabs>
			<x-tabs.tab-item :active="$tab == 'email'" href="{{ route('admin.config.email') }}" textLabel="{{ __('admin/sidebar.email_settings') }}"></x-tabs.tab-item>
			<x-tabs.tab-item :active="$tab == 'testing'" href="{{ route('admin.config.email', ['tab' => 'testing']) }}" textLabel="{{ __('admin/sidebar.email_testing') }}"></x-tabs.tab-item>
		</x-tabs.tabs>
	</div>
	@if ($tab == 'email')
		<x-sided-content>
            <x-slot:sideContent>
                <x-config.smtp-solutions></x-config.smtp-solutions>
            </x-slot:sideContent>
            @livewire('admin.config.email')
		</x-sided-content>
	@elseif ($tab == 'testing')
		<x-content>
			@include('admin::config.email.parts.testing')
		</x-content>
	@endif
@endsection
