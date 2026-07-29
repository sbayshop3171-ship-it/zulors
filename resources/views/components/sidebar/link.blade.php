@props([
	'target' => '_blank',
	'href' => '#',
	'classes' => ''
])

<a href="{{ $href }}" target="{{ $target }}" class="text-cap-l text-lab-sc mr-2 hover:underline">
	{{ $slot }}
</a>