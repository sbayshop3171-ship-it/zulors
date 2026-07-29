@php
	$layoutSide = request()->attributes->get('layoutSide', 'center');
@endphp

<div class="fixed inset-0 z-50 overflow-y-auto bg-black/15 backdrop-blur-xs">
	<div class="flex min-h-full items-center justify-center p-4 {{ $layoutSide == 'left' ? 'lg:ml-sidebar-width lg:justify-start lg:pl-16' : '' }}">
		<div class="w-full max-w-[calc(100vw-2rem)] shrink-0 overflow-hidden rounded-2xl bg-bg-pr shadow-xs sm:max-w-[32rem] lg:max-w-[40rem]">
			{{ $slot }}
		</div>
	</div>
</div>
