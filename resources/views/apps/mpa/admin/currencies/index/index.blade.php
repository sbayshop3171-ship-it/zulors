@extends('adminLayout::index')

@section('pageContent')
	<x-page-title titleText=" {{ __('admin/currencies.index_title') }}"></x-page-title>

	<x-table.table>
		<x-table.thead>
			<x-table.th>{{ __('table.labels.name') }}</x-table.th>
			<x-table.th>{{ __('table.labels.code') }} Alpha 3</x-table.th>
			<x-table.th>{{ __('table.labels.status') }}</x-table.th>
			<x-table.th>{{ __('table.labels.created_at') }}</x-table.th>
			<x-table.th>#ID</x-table.th>
			<x-table.th>{{ __('labels.table.actions') }}</x-table.th>
		</x-table.thead>
		<x-table.tbody>
			@if($currencies->isNotEmpty())
				@foreach ($currencies as $currencyData)
					@include('admin::currencies.index.parts.currency-item', [
						'currencyData' => $currencyData
					])
				@endforeach
			@else
				<x-table.empty colspan="7"></x-table.empty>
			@endif
		</x-table.tbody>
	</x-table.table>

	@unless($currencies->isEmpty())
		<div class="mt-4">
			{{ $currencies->onEachSide(1)->withQueryString()->links('pagination.index') }}
		</div>
	@endunless
@endsection
