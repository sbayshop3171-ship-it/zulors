@extends('adminLayout::index')

@section('pageContent')
    <x-page-title titleText=" {{ __('admin/reports.index_title') }}"></x-page-title>

	<x-table.table>
		<x-table.thead>
			<x-table.th>{{ __('table.labels.reported_by') }}</x-table.th>
			<x-table.th>{{ __('table.labels.reason') }}</x-table.th>
			<x-table.th>{{ __('table.labels.content') }}</x-table.th>
			<x-table.th>{{ __('table.labels.status') }}</x-table.th>
			<x-table.th>{{ __('table.labels.date') }}</x-table.th>
			<x-table.th>#ID</x-table.th>
			<x-table.th>{{ __('labels.table.actions') }}</x-table.th>
		</x-table.thead>
		<x-table.tbody>
			@if($reports->isNotEmpty())
				@foreach ($reports as $reportData)
					@include('admin::reports.index.parts.report-item', [
						'reportData' => $reportData
					])
				@endforeach
			@else
				<x-table.empty colspan="7"></x-table.empty>
			@endif
		</x-table.tbody>
	</x-table.table>

	@unless($reports->isEmpty())
		<div class="mt-4">
			{{ $reports->onEachSide(1)->withQueryString()->links('pagination.index') }}
		</div>
	@endunless
@endsection
