@extends('adminLayout::index')

@section('pageContent')
    <x-page-title titleText=" {{ __('admin/config.ffmpeg_settings') }}"></x-page-title>

    <div class="mb-4">
		<x-tabs.tabs>
			<x-tabs.tab-item :active="$tab == 'ffmpeg'" href="{{ route('admin.config.ffmpeg') }}" textLabel="{{ __('admin/sidebar.ffmpeg_settings') }}">
            </x-tabs.tab-item>
            <x-tabs.tab-item :active="$tab == 'ffmpeg-test'" href="{{ route('admin.config.ffmpeg', ['tab' => 'ffmpeg-test']) }}" textLabel="{{ __('admin/sidebar.ffmpeg_testing') }}">
            </x-tabs.tab-item>
		</x-tabs.tabs>
	</div>

    <x-sided-content>
        <x-slot:sideContent>
            <x-config.ffmpeg></x-config.ffmpeg>
        </x-slot:sideContent>

        @if ($tab == 'ffmpeg')
            @livewire('admin.config.ffmpeg')
        @elseif ($tab == 'ffmpeg-test')
            @livewire('admin.config.ffmpeg-test')
        @endif
    </x-sided-content>
@endsection
