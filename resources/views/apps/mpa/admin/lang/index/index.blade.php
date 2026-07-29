@extends('adminLayout::index')

@section('pageContent')
    <x-page-title titleText=" {{ __('admin/lang.index_title') }}"></x-page-title>

	<div class="mb-6">
		<x-tabs.tabs>
			<x-tabs.tab-item :active="route_is('admin.lang.index')" href="{{ route('admin.lang.index') }}" textLabel="{{ __('admin/lang.tabs.all') }}"></x-tabs.tab-item>
			<x-tabs.tab-item :active="route_is('admin.lang.create')" href="{{ route('admin.lang.create') }}" textLabel="{{ __('admin/lang.tabs.add') }}"></x-tabs.tab-item>
		</x-tabs.tabs>
	</div>

	<x-table.table>
		<x-table.thead>
			<x-table.th>{{ __('table.labels.name') }}</x-table.th>
			<x-table.th>Alpha 2</x-table.th>
			<x-table.th>{{ __('table.labels.direction') }}</x-table.th>
			<x-table.th>{{ __('table.labels.status') }}</x-table.th>
			<x-table.th>{{ __('table.labels.usage') }}</x-table.th>
			<x-table.th>{{ __('table.labels.default') }}</x-table.th>
			<x-table.th>#ID</x-table.th>
			<x-table.th>{{ __('labels.table.actions') }}</x-table.th>
		</x-table.thead>
		<x-table.tbody>
			@if($languages->isNotEmpty())
				@foreach ($languages as $languageData)
					@include('admin::lang.index.parts.lang-item', [
						'languageData' => $languageData
					])
				@endforeach
			@else
				<x-table.empty colspan="9"></x-table.empty>
			@endif
		</x-table.tbody>
	</x-table.table>

	@unless($languages->isEmpty())
		<div class="mt-4">
			{{ $languages->onEachSide(1)->withQueryString()->links('pagination.index') }}
		</div>
	@endunless
@endsection
