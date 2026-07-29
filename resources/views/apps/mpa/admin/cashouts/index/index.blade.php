@extends('adminLayout::index')

@section('pageContent')
    <x-page-title titleText=" {{ __('admin/cashouts.index_title') }}"></x-page-title>

    <div class="mb-4">
		<x-tabs.tabs>
			<x-tabs.tab-item :active="$filters['status'] == 'all'" href="{{ route('admin.cashouts.index') }}" textLabel="{{ __('admin/filter.filters.cashouts.tabs.all') }}"></x-tabs.tab-item>
			<x-tabs.tab-item :active="$filters['status'] == 'paid'" href="{{ route('admin.cashouts.index', ['status' => 'paid']) }}" textLabel="{{ __('admin/filter.filters.cashouts.tabs.paid') }}"></x-tabs.tab-item>
			<x-tabs.tab-item :active="$filters['status'] == 'pending'" href="{{ route('admin.cashouts.index', ['status' => 'pending']) }}" textLabel="{{ __('admin/filter.filters.cashouts.tabs.pending') }}"></x-tabs.tab-item>
			<x-tabs.tab-item :active="$filters['status'] == 'rejected'" href="{{ route('admin.cashouts.index', ['status' => 'rejected']) }}" textLabel="{{ __('admin/filter.filters.cashouts.tabs.rejected') }}"></x-tabs.tab-item>
			<x-tabs.tab-item :active="$filters['status'] == 'cancelled'" href="{{ route('admin.cashouts.index', ['status' => 'cancelled']) }}" textLabel="{{ __('admin/filter.filters.cashouts.tabs.cancelled') }}"></x-tabs.tab-item>
		</x-tabs.tabs>
	</div>

	<x-table.table>
		<x-slot:filter>
			<div class="mb-4">
				<form action="{{ route('admin.cashouts.index') }}" method="GET">
					<x-search.searchbar :value="$filters['search']" :cancelUrl="route('admin.cashouts.index')" />
					<div class="mt-1">
						<x-search.desc description="{{ __('admin/filter.filters.cashouts.description') }}" />
					</div>
				</form>
			</div>
		</x-slot:filter>
		<x-table.thead>
			<x-table.th>{{ __('table.labels.author') }}</x-table.th>
			<x-table.th>{{ __('table.labels.request_code') }}</x-table.th>
			<x-table.th>{{ __('table.labels.amount') }}</x-table.th>
			<x-table.th>{{ __('table.labels.method') }}</x-table.th>
			<x-table.th>{{ __('table.labels.status') }}</x-table.th>
			<x-table.th>{{ __('table.labels.currency') }}</x-table.th>
			<x-table.th>{{ __('table.labels.created_at') }}</x-table.th>
			<x-table.th>{{ __('labels.table.actions') }}</x-table.th>
		</x-table.thead>
		<x-table.tbody>
			@if($cashouts->isNotEmpty())
				@foreach ($cashouts as $cashoutData)
					@include('admin::cashouts.index.parts.cashout-item', [
						'cashoutData' => $cashoutData
					])
				@endforeach
			@else
				<x-table.empty colspan="8"></x-table.empty>
			@endif
		</x-table.tbody>
	</x-table.table>

	@unless($cashouts->isEmpty())
		<div class="mt-4">
			{{ $cashouts->onEachSide(1)->withQueryString()->links('pagination.index') }}
		</div>
	@endunless
@endsection
