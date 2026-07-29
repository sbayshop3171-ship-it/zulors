<x-table.tr>
	<x-table.td variant="strong" weight="medium">
		<x-table.avatar :avatarSrc="$reportData->reporter->avatar_url" :name="$reportData->reporter->name" :link="route('admin.users.show', $reportData->reporter->id)" />
	</x-table.td>
	<x-table.td variant="muted">
		@if(isset($reportData->reason['description']))
			<a href="{{ route('admin.reports.show', $reportData->id) }}" class="hover:underline">
				{{ truncate_text($reportData->reason['description'], 32) }}
			</a>
		@else
			<x-table.empty-cell></x-table.empty-cell>
		@endif
	</x-table.td>
	<x-table.td variant="muted">
		{{ $reportData->type->label() }} {{ $reportData->type->emoji() }}
	</x-table.td>
	<x-table.td variant="muted">
		{{ $reportData->status->label() }} {{ $reportData->status->emoji() }}
	</x-table.td>
	<x-table.td variant="muted">
		{{ $reportData->created_at->getFormatted() }}
	</x-table.td>
	<x-table.td variant="muted">
		{{ $reportData->id }}
	</x-table.td>
	<x-table.td>
		<div class="flex justify-end">
			<a href="{{ route('admin.reports.show', $reportData->id) }}">
				<x-ui.buttons.icon iconName="arrow-up-right" iconType="line"></x-ui.buttons.icon>
			</a>
		</div>
	</x-table.td>
</x-table.tr>