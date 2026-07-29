@props([
	'title' => null,
	'caption' => null,
	'captionTitle' => null,
])

<div class="flex justify-between">
	<div class="leading-none">
		<h4 class="text-par-m font-bold text-lab-pr">
			{{ $title }}
		</h4>
	</div>
	@if($caption)
		<div class="leading-none text-right">
			@if($captionTitle)
				<h6 class="text-par-n text-lab-pr2 font-medium mb-1.5">
					{{ $captionTitle }}
				</h6>
			@endif

			<p class="text-cap-l text-lab-sc">
				{{ $caption }}
			</p>
		</div>
	@endif
</div>
