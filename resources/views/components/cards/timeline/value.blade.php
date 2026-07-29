@props([
	'value' => null,
	'color' => 'text-lab-sc',
	'tag' => 'p',
	'rightAlign' => false
])

<{{ $tag }} class="block text-par-s truncate {{ $color }} {{ $rightAlign ? 'text-right' : 'text-left' }}" {{ $attributes }}>
	{{ $value }}
</{{ $tag }}>