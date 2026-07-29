@props([
	'name' => '',
    'url' => null,
    'size' => 'size-8',
])

<div class="{{ $size }} rounded-full overflow-hidden border border-bord-pr">
	<img src="{{ empty($url) ? asset("assets/icons/avatar/{$name}.png") : $url }}" alt="Icon" class="size-full">
</div>
