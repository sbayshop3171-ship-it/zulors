@props([
	'placeholder' => __('admin/filter.search_placeholder'),
	'value' => '',
	'cancelUrl' => '',
	'isClearable' => true,
])

<div class="block">
	<div class="flex border border-bord-card rounded-full overflow-hidden">
		<div class="flex-1 overflow-hidden">
			<input name="search" value="{{ $value }}" type="text" placeholder="{{ $placeholder }}" class="h-10 bg-transparent block w-full overflow-hidden outline-hidden text-par-l text-lab-pr px-4">
		</div>
		@if($isClearable && $value)
			<div class="shrink-0 overflow-hidden max-w-40">
				<a href="{{ $cancelUrl }}" class="h-10 w-full text-red-900 smoothing inline-flex-center outline-hidden px-4 cursor-pointer">
					<span class="text-par-m font-medium truncate">
						{{ __('admin/filter.clear_search') }}
					</span>
				</a>
			</div>
		@endif
		<div class="shrink-0 overflow-hidden min-w-26">
			<button type="submit" class="h-10 w-full text-lab-pr2 smoothing hover:bg-fill-tr block outline-hidden px-4 bg-fill-pr cursor-pointer">
				<span class="text-par-m font-medium">
					{{ __('admin/filter.search_button') }}
				</span>
			</button>
		</div>
	</div>
</div>