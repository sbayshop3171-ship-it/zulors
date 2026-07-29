@extends('adminLayout::index')

@section('pageContent')
	<div x-data="marketIndex">
		<x-page-title titleText=" {{ __('admin/market.index_title') }}"></x-page-title>

		<div class="mb-4">
			<x-tabs.tabs>
				<x-tabs.tab-item :active="$filters['approval'] == 'all'" href="{{ route('admin.market.index') }}" textLabel="{{ __('admin/filter.filters.market.tabs.all') }}"></x-tabs.tab-item>
				<x-tabs.tab-item :active="$filters['approval'] == 'approved'" href="{{ route('admin.market.index', ['approval' => 'approved']) }}" textLabel="{{ __('admin/filter.filters.market.tabs.approved') }}"></x-tabs.tab-item>
				<x-tabs.tab-item :active="$filters['approval'] == 'rejected'" href="{{ route('admin.market.index', ['approval' => 'rejected']) }}" textLabel="{{ __('admin/filter.filters.market.tabs.rejected') }}"></x-tabs.tab-item>
				<x-tabs.tab-item :active="$filters['approval'] == 'pending'" href="{{ route('admin.market.index', ['approval' => 'pending']) }}" textLabel="{{ __('admin/filter.filters.market.tabs.pending') }}"></x-tabs.tab-item>
			</x-tabs.tabs>
		</div>

		<x-table.table>
			<x-slot:filter>
				<div class="mb-4">
					<form action="{{ route('admin.market.index') }}" method="GET">
						<x-search.searchbar :value="$filters['search']" :cancelUrl="route('admin.market.index')" />
						<div class="mt-1">
							<x-search.desc description="{{ __('admin/filter.filters.market.description') }}" />
						</div>
					</form>
				</div>
			</x-slot:filter>
			<x-table.thead>
				<x-table.th>{{ __('table.labels.seller') }}</x-table.th>
				<x-table.th>{{ __('table.labels.title') }}</x-table.th>
				<x-table.th>{{ __('table.labels.category') }}</x-table.th>
				<x-table.th>{{ __('table.labels.approval') }}</x-table.th>
				<x-table.th>{{ __('table.labels.status') }}</x-table.th>
				<x-table.th>{{ __('table.labels.price') }}</x-table.th>
				<x-table.th>{{ __('table.labels.created_at') }}</x-table.th>
				<x-table.th>#ID</x-table.th>
				<x-table.th>{{ __('labels.table.actions') }}</x-table.th>
			</x-table.thead>
			<x-table.tbody>
				@if($products->isNotEmpty())
					@foreach ($products as $productData)
						@include('admin::market.index.parts.product-item', [
							'productData' => $productData
						])
					@endforeach
				@else
					<x-table.empty colspan="9"></x-table.empty>
				@endif
			</x-table.tbody>
		</x-table.table>

		@unless($products->isEmpty())
			<div class="mt-4">
				{{ $products->onEachSide(1)->withQueryString()->links('pagination.index') }}
			</div>
		@endunless
	</div>
	<script>
		window.addEventListener('alpine:init', () => {
			Alpine.data('marketIndex', () => {
				return {
					approveProduct: (formAction) => {
						Alpine.store('confirmModal').open({
							title: "{{ __('admin/prompt.approve_product.title') }}",
							desc: "{{ __('admin/prompt.approve_product.description') }}",
							formAction: formAction,
							confirmButtonText: "{{ __('admin/prompt.approve_product.confirm_btn_text') }}"
						});
					},
					rejectProduct: (formAction) => {
						Alpine.store('confirmModal').open({
							title: "{{ __('admin/prompt.reject_product.title') }}",
							desc: "{{ __('admin/prompt.reject_product.description') }}",
							formAction: formAction,
							confirmButtonText: "{{ __('admin/prompt.reject_product.confirm_btn_text') }}"
						});
					}
				}
			});
		});
	</script>
@endsection
