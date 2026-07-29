@props(['storyData'])

<x-card>
	<div class="overflow-hidden rounded-2xl">
		<div class="overflow-hidden aspect-square relative">
			@if($storyData->type->isImage())
				<img src="{{ $storyData->media->first()->source_url }}" alt="Image" class="size-full object-cover">
			@elseif($storyData->type->isVideo())
				<img src="{{ $storyData->media->first()->thumbnail_url }}" alt="Image" class="size-full object-cover">
			@endif

			@if($storyData->type->isVideo())
				<x-overlay.video-play></x-overlay.video-play>
			@endif
		</div>
		<div class="p-4 overflow-hidden">
			@if($storyData->content)
				<p class="text-lab-sc text-par-m mb-2 line-clamp-6 leading-snug">
					{{ nl2br($storyData->content) }}
				</p>
			@endif

			<p class="text-lab-sc text-par-m">
				{{ $storyData->created_at->getCalendar() }}
			</p>

			@unless($storyData->isExpired())
				<a href="{{ $storyData->story->url }}" class="block mt-4" target="_blank">
					<x-ui.buttons.pill size="sm" type="button" width="w-full" btnText="{{ __('admin/dd.story.view_story') }}"></x-ui.buttons.pill>
				</a>
			@endif
		</div>
	</div>
</x-card>
