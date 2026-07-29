<x-table.tr>
	<x-table.td variant="strong" weight="medium">
		<x-table.avatar :avatarSrc="$postData->user->avatar_url" :name="$postData->user->name" :link="route('admin.users.show', $postData->user->id)" />
	</x-table.td>
	<x-table.td variant="muted">
		@if(empty($postData->content))
			<x-table.empty-cell />
		@else
			<a class="hover:underline" href="{{ route('admin.posts.show', $postData->id) }}">
				{{ truncate_text($postData->content, 22) }}
			</a>
		@endif
	</x-table.td>
	<x-table.td variant="muted">
		{{ $postData->type->label() }}
	</x-table.td>
	<x-table.td variant="muted">
		{{ $postData->comments_count }}
	</x-table.td>
	<x-table.td variant="muted">
		{{ $postData->views_count }}
	</x-table.td>
	<x-table.td variant="muted">
		{{ $postData->created_at->getFormatted() }}
	</x-table.td>
	<x-table.td variant="muted" :numeric="true">
		{{ $postData->id }}
	</x-table.td>
	<x-table.td>
		<div class="flex justify-end">
			<a href="{{ route('admin.posts.show', $postData->id) }}">
				<x-ui.buttons.icon iconName="arrow-up-right" iconType="line"></x-ui.buttons.icon>
			</a>
		</div>
	</x-table.td>
</x-table.tr>