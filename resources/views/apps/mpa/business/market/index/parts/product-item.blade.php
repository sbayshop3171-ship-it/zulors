<x-table.tr>
	<x-table.td>
		<div class="flex items-center">
			<div class="shrink-0">
				<div class="size-8 rounded-xs border border-fill-pr overflow-hidden">
					<img class="w-full" src="{{ $productData->preview_image_url }}" alt="Image">
				</div>
			</div>
			<a href="{{ route('business.market.show', $productData->id) }}" class="truncate line-clamp-1 ml-2 block hover:text-brand-900 smoothing">
				{{ $productData->title }}
			</a>
		</div>
	</x-table.td>
	<x-table.td>
		{{ $productData->stock_quantity }}
	</x-table.td>
	<x-table.td>
		{{ $productData->formatted_price }}
	</x-table.td>
	<x-table.td>
		<x-badge variant="{{ $productData->status->badgeVariant() }}">
			{{ $productData->status->label() }} {{ $productData->status->emoji() }}
		</x-badge>
	</x-table.td>
	<x-table.td>
		<x-badge variant="{{ $productData->approval->badgeVariant() }}">
			{{ $productData->approval->label() }} {{ $productData->approval->emoji() }}
		</x-badge>
	</x-table.td>
	<x-table.td>
		<span class="text-lab-sc">
			{{ $productData->category_name }}
		</span>
	</x-table.td>
	<x-table.td>
		<span class="text-lab-sc">
			{{ $productData->type->label() }}
		</span>
	</x-table.td>
	<x-table.td>
		<div class="flex justify-end">
			<x-ui.dropdown.dropdown>
				<x-slot:dropdownButton>
					<span class="opacity-50 hover:opacity-100">
						<x-ui.buttons.icon></x-ui.buttons.icon>
					</span>
				</x-slot:dropdownButton>

				<x-ui.dropdown.item tag="a" href="{{ route('business.market.show', $productData->id) }}" itemText="{{ __('business/dd.product.view_product') }}">
					<x-slot:itemIcon>
						<x-ui-icon type="line" name="layout-alt-02"></x-ui-icon>
					</x-slot:itemIcon>
				</x-ui.dropdown.item>
				<x-ui.dropdown.item tag="a" href="{{ route('business.market.edit', $productData->id) }}" itemText="{{ __('business/dd.product.edit_product') }}">
					<x-slot:itemIcon>
						<x-ui-icon type="line" name="edit-03"></x-ui-icon>
					</x-slot:itemIcon>
				</x-ui.dropdown.item>
				<x-div></x-div>
				<x-ui.dropdown.item tag="a" itemText="{{ __('business/dd.product.open_product') }}" href="{{ $productData->url }}">
					<x-slot:itemIcon>
						<x-ui-icon type="line" name="arrow-up-right"></x-ui-icon>
					</x-slot:itemIcon>
				</x-ui.dropdown.item>
				<x-div></x-div>
				<x-ui.dropdown.item tag="button" type="button" :danger="true" x-on:click="deleteProduct('{{ route('business.market.destroy', $productData->id) }}')" itemText="{{ __('business/dd.product.delete_product') }}">
					<x-slot:itemIcon>
						<x-ui-icon type="line" name="trash-04"></x-ui-icon>
					</x-slot:itemIcon>
				</x-ui.dropdown.item>
			</x-ui.dropdown.dropdown>
		</div>
	</x-table.td>
</x-table.tr>
