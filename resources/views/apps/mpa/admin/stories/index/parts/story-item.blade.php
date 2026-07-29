<x-table.tr>
	<x-table.td variant="strong" weight="medium">
		<x-table.avatar :avatarSrc="$storyData->story->user->avatar_url" :name="$storyData->story->user->name" :link="route('admin.users.show', $storyData->story->user->id)" />
	</x-table.td>
	<x-table.td>
		@if($storyData->media->isNotEmpty())
			<a class="block" href="{{ route('admin.stories.show', $storyData->id) }}">
				<div class="flex items-center gap-2">
					<x-table.image :imageSrc="$storyData->media->first()->lqip_base64"></x-table.image>

					<span>
						{{ $storyData->type->label() }}
					</span>
				</div>
			</a>
		@else
			<x-table.empty-cell></x-table.empty-cell>
		@endif
	</x-table.td>
	<x-table.td variant="muted" :numeric="true">
		{{ $storyData->views_count }}
	</x-table.td>
	<x-table.td variant="muted">
		{{ $storyData->created_at->getFormatted() }}
	</x-table.td>
	<x-table.td variant="muted">
		{{ $storyData->expires_at->getFormatted() }}
	</x-table.td>
	<x-table.td variant="muted" :numeric="true">
		{{ $storyData->id }}
	</x-table.td>
	<x-table.td>
		<div class="flex justify-end">
			<a href="{{ route('admin.stories.show', $storyData->id) }}">
				<x-ui.buttons.icon iconName="arrow-up-right" iconType="line"></x-ui.buttons.icon>
			</a>
		</div>
	</x-table.td>
</x-table.tr>