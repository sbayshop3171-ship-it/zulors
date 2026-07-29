@extends('adminLayout::index')

@section('pageContent')
    <x-page-title titleText=" {{ __('admin/authorship.index_title') }}"></x-page-title>

	<x-table.table>
		<x-table.thead>
			<x-table.th>{{ __('table.labels.requested_by') }}</x-table.th>
			<x-table.th>{{ __('table.labels.status') }}</x-table.th>
			<x-table.th>{{ __('table.labels.date') }}</x-table.th>
			<x-table.th>#ID</x-table.th>
			<x-table.th>{{ __('labels.table.actions') }}</x-table.th>
		</x-table.thead>
		<x-table.tbody>
			@if($authorshipRequests->isNotEmpty())
				@foreach ($authorshipRequests as $requestData)
					@include('admin::authorship.index.parts.request-item', [
						'requestData' => $requestData
					])
				@endforeach
			@else
				<x-table.empty colspan="5"></x-table.empty>
			@endif
		</x-table.tbody>
	</x-table.table>

	@unless($authorshipRequests->isEmpty())
		<div class="mt-4">
			{{ $authorshipRequests->onEachSide(1)->withQueryString()->links('pagination.index') }}
		</div>
	@endunless
@endsection
