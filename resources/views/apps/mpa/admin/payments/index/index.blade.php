@extends('adminLayout::index')

@section('pageContent')
    <x-page-title titleText=" {{ __('admin/payments.index_title') }}"></x-page-title>

	<x-table.table>
		<x-table.thead>
			<x-table.th>{{ __('table.labels.user') }}</x-table.th>
			<x-table.th>{{ __('table.labels.amount') }}</x-table.th>
			<x-table.th>{{ __('table.labels.type') }}</x-table.th>
			<x-table.th>{{ __('table.labels.method') }}</x-table.th>
			<x-table.th>{{ __('table.labels.status') }}</x-table.th>
			<x-table.th>{{ __('table.labels.created_at') }}</x-table.th>
			<x-table.th>#ID</x-table.th>
			<x-table.th>{{ __('labels.table.actions') }}</x-table.th>
		</x-table.thead>
		<x-table.tbody>
			@if($payments->isNotEmpty())
				@foreach ($payments as $paymentData)
					@include('admin::payments.index.parts.payment-item', [
						'paymentData' => $paymentData
					])
				@endforeach
			@else
				<x-table.empty colspan="9"></x-table.empty>
			@endif
		</x-table.tbody>
	</x-table.table>

	@unless($payments->isEmpty())
		<div class="mt-4">
			{{ $payments->onEachSide(1)->withQueryString()->links('pagination.index') }}
		</div>
	@endunless
@endsection
