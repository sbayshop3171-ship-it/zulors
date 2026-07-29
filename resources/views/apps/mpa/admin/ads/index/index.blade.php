@extends('adminLayout::index')

@section('pageContent')
    <x-page-title titleText=" {{ __('admin/ads.index_title') }}"></x-page-title>

	<div class="mb-4">
		<x-tabs.tabs>
			<x-tabs.tab-item :active="$filters['approval'] == 'all'" href="{{ route('admin.ads.index') }}" textLabel="{{ __('admin/filter.filters.ads.tabs.all') }}"></x-tabs.tab-item>
			<x-tabs.tab-item :active="$filters['approval'] == 'approved'" href="{{ route('admin.ads.index', ['approval' => 'approved']) }}" textLabel="{{ __('admin/filter.filters.ads.tabs.approved') }}"></x-tabs.tab-item>
			<x-tabs.tab-item :active="$filters['approval'] == 'rejected'" href="{{ route('admin.ads.index', ['approval' => 'rejected']) }}" textLabel="{{ __('admin/filter.filters.ads.tabs.rejected') }}"></x-tabs.tab-item>
			<x-tabs.tab-item :active="$filters['approval'] == 'pending'" href="{{ route('admin.ads.index', ['approval' => 'pending']) }}" textLabel="{{ __('admin/filter.filters.ads.tabs.pending') }}"></x-tabs.tab-item>
		</x-tabs.tabs>
	</div>

	<x-table.table>
		<x-slot:filter>
			<div class="mb-4">
				<form action="{{ route('admin.ads.index') }}" method="GET">
					<x-search.searchbar :value="$filters['search']" :cancelUrl="route('admin.ads.index')" />
					<div class="mt-1">
						<x-search.desc description="{{ __('admin/filter.filters.ads.description') }}" />
					</div>
				</form>
			</div>
		</x-slot:filter>
		<x-table.thead>
			<x-table.th>{{ __('table.labels.author') }}</x-table.th>
			<x-table.th>{{ __('table.labels.title') }}</x-table.th>
			<x-table.th>{{ __('table.labels.status') }}</x-table.th>
			<x-table.th>{{ __('table.labels.approval') }}</x-table.th>
			<x-table.th>{{ __('table.labels.total_budget') }}</x-table.th>
			<x-table.th>{{ __('table.labels.spends') }}</x-table.th>
			<x-table.th>#ID</x-table.th>
			<x-table.th>{{ __('labels.table.actions') }}</x-table.th>
		</x-table.thead>
		<x-table.tbody>
			@if($ads->isNotEmpty())
				@foreach ($ads as $adData)
					@include('admin::ads.index.parts.ad-item', [
						'adData' => $adData
					])
				@endforeach
			@else
				<x-table.empty colspan="8"></x-table.empty>
			@endif
		</x-table.tbody>
	</x-table.table>

	@unless($ads->isEmpty())
		<div class="mt-4">
			{{ $ads->onEachSide(1)->withQueryString()->links('pagination.index') }}
		</div>
	@endunless
@endsection
