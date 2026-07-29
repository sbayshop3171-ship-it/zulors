<x-card>
	<div class="p-4">
		<div class="mb-4">
			<x-entity.format format="payment"></x-entity.format>
		</div>
		<h4 class="text-par-l font-semibold mb-1 text-lab-pr2">
			{{ __('admin/info.payment_preview.title') }}
		</h4>
		<p class="text-lab-sc text-par-m mb-2">
			{{ __('admin/info.payment_preview.line_one') }}
		</p>
		<p class="text-lab-sc text-par-m mb-2">
			{{ __('admin/info.payment_preview.line_two') }}
		</p>
		<p class="text-lab-sc text-par-m">
			{{ __('admin/info.payment_preview.line_three') }}
		</p>
	</div>
</x-card>
