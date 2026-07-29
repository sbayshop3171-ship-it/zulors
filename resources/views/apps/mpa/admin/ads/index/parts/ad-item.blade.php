<x-table.tr>
	<x-table.td variant="strong" weight="medium">
		<x-table.avatar :avatarSrc="$adData->user->avatar_url" :name="$adData->user->name" :link="route('admin.users.show', $adData->user->id)" />
	</x-table.td>
	<x-table.td variant="muted">
		<a href="{{ route('admin.ads.show', $adData->id) }}" class="hover:underline">	
			{{ truncate_text($adData->title, 22) }}
		</a>
	</x-table.td>
	<x-table.td variant="muted">
		{{ $adData->status->label() }} {{ $adData->status->emoji() }}
	</x-table.td>
	<x-table.td variant="muted">
		{{ $adData->approval->label() }} {{ $adData->approval->emoji() }}
	</x-table.td>
	<x-table.td variant="strong" weight="medium">
		{{ $adData->formatted_total_budget }}
	</x-table.td>
	<x-table.td variant="strong" weight="medium">
		{{ $adData->formatted_spent_budget }}
	</x-table.td>
	<x-table.td variant="muted" :numeric="true">
		{{ $adData->id }}
	</x-table.td>
	<x-table.td>
		<div class="flex justify-end">
			<a href="{{ route('admin.ads.show', $adData->id) }}">
				<x-ui.buttons.icon iconName="arrow-up-right" iconType="line"></x-ui.buttons.icon>
			</a>
		</div>
	</x-table.td>
</x-table.tr>