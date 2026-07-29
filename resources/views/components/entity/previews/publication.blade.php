@props(['postData'])

<x-card>
	<div class="overflow-hidden rounded-2xl">
		@if($postData->type->isMedia() && $postData->media->isNotEmpty())
			@if($postData->type->isImage() || $postData->type->isGif())
				<div class="aspect-square">
					<img src="{{ $postData->media->first()->source_url }}" alt="Image" class="size-full object-cover">
				</div>
			@elseif($postData->type->isVideo())
				<x-entity.format format="mp4"></x-entity.format>
			@elseif($postData->type->isAudio())
				<x-entity.format format="mp3"></x-entity.format>
			@elseif($postData->type->isDocument())
				<x-entity.format format="doc"></x-entity.format>
			@endif
		@elseif($postData->type->isPoll())
			<x-entity.format format="poll"></x-entity.format>
		@endif

		<div class="p-4">
			@if($postData->content)
				<div class="mb-4">
					<p class="text-lab-sc text-par-m line-clamp-6 leading-snug">
						{!! nl2br($postData->content) !!}
					</p>
				</div>
			@endif
			<p class="text-lab-sc text-par-m">
				{{ $postData->created_at->getCalendar() }}
			</p>
			<a href="{{ $postData->url }}" class="block mt-4" target="_blank">
				<x-ui.buttons.pill size="sm" type="button" width="w-full" btnText="{{ __('admin/dd.post.view_publication') }}"></x-ui.buttons.pill>
			</a>
		</div>
	</div>
</x-card>
