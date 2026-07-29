<x-table.tr>
	<x-table.td variant="strong" weight="medium">
		<a href="{{ route('admin.currency.show', $currencyData->id) }}" class="hover:underline">
			{{ $currencyData->name }}
		</a>
	</x-table.td>
	<x-table.td variant="muted">
		{{ $currencyData->alpha_3_code }} ({{ $currencyData->symbol }})
	</x-table.td>
	<x-table.td variant="muted">
		@if($currencyData->status)	
			{{ __('labels.status_labels.active') }} ✅
		@else
			{{ __('labels.status_labels.inactive') }} ❌
		@endif
	</x-table.td>
	<x-table.td variant="muted">
		{{ $currencyData->created_at->getDate() }}
	</x-table.td>
	<x-table.td variant="muted" numeric>
		{{ $currencyData->id }}
	</x-table.td>
	<x-table.td>
		<div class="flex justify-end">
			<a href="{{ route('admin.currency.show', $currencyData->id) }}">
				<x-ui.buttons.icon iconName="arrow-up-right" iconType="line"></x-ui.buttons.icon>
			</a>
		</div>
	</x-table.td>
</x-table.tr>