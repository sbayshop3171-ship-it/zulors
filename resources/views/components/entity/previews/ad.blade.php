@props([
    'adData' => null,
])

<x-card>
	<div class="p-4">
		<div class="rounded-lg overflow-hidden mb-3">
			<img class="w-full" src="{{ $adData->preview_image_url }}" alt="Image">
		</div>
		<div class="mb-4">
			<h4 class="text-lab-pr2 text-par-l font-semibold">
				{{ $adData->title }}
			</h4>
			<p class="text-lab-sc text-par-m mb-2">
				{{ $adData->content }}
			</p>

			<p class="text-lab-sc text-par-m">
				{{ $adData->target_url }}
			</p>
			@if($adData->target_topics_text)
				<p class="text-lab-sc text-par-s mt-2">
					{{ $adData->target_topics_text }}
				</p>
			@endif
		</div>
		<a href="{{ $adData->target_url }}" target="_blank">
			<x-ui.buttons.pill size="sm" type="button" width="w-full" btnText="{{ $adData->cta_text }}"></x-ui.buttons.pill>
		</a>
	</div>
</x-card>
