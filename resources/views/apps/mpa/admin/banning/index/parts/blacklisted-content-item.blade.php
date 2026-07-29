<x-table.tr>
	<x-table.td variant="strong" weight="medium">
		@if($blacklistedContentData->admin)
			<a href="{{ route('admin.users.show', $blacklistedContentData->admin->id) }}" class="hover:underline">
				{{ $blacklistedContentData->admin->name }}
			</a>
		@else
			{{ __('labels.unknown') }}
		@endif
	</x-table.td>
	<x-table.td variant="strong" weight="medium">
		<a href="{{ route('admin.banning.show', $blacklistedContentData->id) }}" class="hover:underline">
			{{ $blacklistedContentData->type->label() }} {{ $blacklistedContentData->type->emoji() }}
		</a>
	</x-table.td>
	<x-table.td variant="muted">
		{{ $blacklistedContentData->blacklistable }}
	</x-table.td>
	<x-table.td variant="muted">
		{{ $blacklistedContentData->added_at->getDate() }}
	</x-table.td>
	<x-table.td variant="muted">
		@if($blacklistedContentData->expires_at)
			{{ $blacklistedContentData->expires_at->getDate() }}
		@else
			{{ __('labels.never') }} ❌
		@endif
	</x-table.td>
	<x-table.td variant="muted" numeric>
		{{ $blacklistedContentData->id }}
	</x-table.td>
	<x-table.td>
		<div class="flex justify-end">
			<a href="{{ route('admin.banning.show', $blacklistedContentData->id) }}">
				<x-ui.buttons.icon iconName="arrow-up-right" iconType="line"></x-ui.buttons.icon>
			</a>
		</div>
	</x-table.td>
</x-table.tr>