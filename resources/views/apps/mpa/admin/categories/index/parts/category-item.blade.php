<x-table.tr>
	<x-table.td variant="strong" weight="medium">
		<a href="{{ route('admin.categories.show', $categoryData->id) }}" class="hover:underline">
			{{ $categoryData->category_name }}
		</a>
	</x-table.td>
	<x-table.td variant="strong" weight="medium">
		{{ $categoryData->categorizable_type->label() }} {{ $categoryData->categorizable_type->emoji() }}
	</x-table.td>
	<x-table.td variant="muted" numeric>
		{{ $categoryData->usage_count }}
	</x-table.td>
	<x-table.td variant="muted" numeric>
		{{ $categoryData->depth }}
	</x-table.td>
	<x-table.td variant="muted" numeric>
		{{ $categoryData->id }}
	</x-table.td>
	<x-table.td>
		<div class="flex justify-end">
			<a href="{{ route('admin.categories.show', $categoryData->id) }}">
				<x-ui.buttons.icon iconName="arrow-up-right" iconType="line"></x-ui.buttons.icon>
			</a>
		</div>
	</x-table.td>
</x-table.tr>