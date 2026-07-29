@props([
	'href' => '#',
	'name' => '',
	'caption' => '',
	'avatar' => '',
	'tag' => 'a',
	'footer' => '',
])

<div class="w-full relative">
	<div class="flex gap-2.5 items-center">
		<div class="size-small-avatar overflow-hidden rounded-full border border-bord-pr">
			<img src="{{ $avatar }}" alt="Image">
		</div>
		<div class="flex-1 leading-tight">
			<h2 class="text-par-m font-bold text-lab-pr">
				{{ $name }}
			</h2>
			<p class="text-lab-sc text-par-s">
				{{ $caption }}
			</p>
		</div>
	</div>

	@if($footer)
		{{ $footer }}
	@endif
</div>