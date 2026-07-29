<x-table.tr>
	<x-table.td variant="muted">
		<a href="{{ route('admin.lang.show', $languageData->id) }}" class="hover:underline">	
			{{ $languageData->name }}
		</a>
	</x-table.td>
	<x-table.td variant="muted">
		{{ $languageData->alpha_2_code }}
	</x-table.td>
	<x-table.td variant="muted">
		{{ $languageData->direction }}
	</x-table.td>
	<x-table.td variant="muted">
		@if($languageData->status)
			{{ __('labels.status_labels.active') }} ✅
		@else
			{{ __('labels.status_labels.inactive') }} ❌
		@endif
	</x-table.td>
	<x-table.td variant="muted">
		{{ $languageData->usage_count }}
	</x-table.td>
	<x-table.td variant="muted">
		{{ $languageData->is_default ? '✅' : '❌' }}
	</x-table.td>
	<x-table.td variant="muted" :numeric="true">
		{{ $languageData->id }}
	</x-table.td>
	<x-table.td>
		<div class="flex justify-end">
			<a href="{{ route('admin.lang.show', $languageData->id) }}">
				<x-ui.buttons.icon iconName="arrow-up-right" iconType="line"></x-ui.buttons.icon>
			</a>
		</div>
	</x-table.td>
</x-table.tr>