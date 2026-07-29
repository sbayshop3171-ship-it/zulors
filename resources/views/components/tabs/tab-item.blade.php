@props([
	'active' => false,
	'textLabel' => '',
	'link' => ''
])

<a {{ $attributes }} class="rounded-md leading-none cursor-pointer {{ $active ? 'bg-bg-pr text-brand-900 shadow-xs' : 'text-lab-sc'}}">
	<span class="text-par-n inline-block font-semibold px-4 py-3 whitespace-nowrap">
		{{ $textLabel }}
	</span>
</a>
