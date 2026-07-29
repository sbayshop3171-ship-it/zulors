@props([
	'width' => 'w-content',
])

<div class="{{ $width }} bg-bg-pr rounded-2xl p-6 border border-bord-sc">
	{{ $slot }}
</div>
