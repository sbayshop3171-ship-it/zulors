@props(['productData'])
<x-card>
	<div class="p-4">
		<div class="rounded-lg overflow-hidden mb-3">
			<img class="w-full" src="{{ $productData->preview_image_url }}" alt="Image">
		</div>
		<div class="mb-2">
			<h4 class="text-lab-sc text-par-n font-medium mb-1 line-clamp-2 leading-tight">
				{{ $productData->title }}
			</h4>
			<p class="text-lab-sc text-par-m">
				{{ $productData->category_name }}
			</p>
			<div class="block">
				@if($productData->hasDiscount())
					<span class="text-lab-pr2 text-par-l font-semibold">
						{{ $productData->formatted_sale_price }}
					</span>
					<span class="text-lab-sc text-par-m line-through">
						{{ $productData->formatted_price }}
					</span>
				@else
					<span class="text-lab-pr2 text-par-l font-semibold">
						{{ $productData->formatted_price }}
					</span>
				@endif
			</div>
		</div>
		<a href="{{ $productData->url }}" target="_blank">
			<x-ui.buttons.pill size="sm" width="w-full" type="button" btnText="{{ __('business/dd.product.open_product') }}"></x-ui.buttons.pill>
		</a>
	</div>
</x-card>
