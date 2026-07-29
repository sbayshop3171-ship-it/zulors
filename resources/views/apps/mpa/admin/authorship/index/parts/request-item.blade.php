<x-table.tr>
	<x-table.td variant="strong" weight="medium">
		<x-table.avatar :avatarSrc="$requestData->user->avatar_url" :name="$requestData->user->name" :link="route('admin.users.show', $requestData->user->id)" />
	</x-table.td>
	<x-table.td variant="muted">
		{{ $requestData->status->label() }} {{ $requestData->status->emoji() }}
	</x-table.td>
	<x-table.td variant="muted">
		{{ $requestData->created_at->getFormatted() }}
	</x-table.td>
	<x-table.td variant="muted" numeric>
		{{ $requestData->id }}
	</x-table.td>
	<x-table.td>
		<div class="flex justify-end">
			<a href="{{ route('admin.users.show', $requestData->user->id) }}">
				<x-ui.buttons.icon iconName="arrow-up-right" iconType="line"></x-ui.buttons.icon>
			</a>
		</div>
	</x-table.td>
</x-table.tr>