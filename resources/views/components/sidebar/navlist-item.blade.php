@props([
	'href' => '#',
	'iconName' => 'x',
	'iconType' => 'line',
	'text' => 'Navlist Item',
	'current' => false,
	'tag' => 'a',
	'muted' => false,
	'trailingIcon' => false,
	'trailingIconName' => 'chevron-right',
	'trailingIconType' => 'line',
])

<{{ $tag }} href="{{ $href }}" class="flex overflow-hidden cursor-pointer items-center {{ $muted ? 'opacity-50' : '' }} {{ $current ? 'sidenav-active' : 'sidenav-inactive' }}" {{ $attributes }}>
	<span class="size-6">
		<x-ui-icon type="{{ $iconType }}" name="{{ $iconName }}"></x-ui-icon>
	</span>
	<span class="text-par-m ml-3 font-medium flex-1">
		{!! $text !!}
	</span>

	@if($trailingIcon)
		<span class="size-icon-small shrink-0">
			<x-ui-icon type="{{ $trailingIconType }}" name="{{ $trailingIconName }}"></x-ui-icon>
		</span>
	@endif
</{{ $tag }}>
