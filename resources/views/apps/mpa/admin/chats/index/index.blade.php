@extends('adminLayout::index')

@section('pageContent')
    <x-page-title titleText=" {{ __('admin/chats.index_title') }}"></x-page-title>

    <div class="mb-4">
		<x-tabs.tabs>
			<x-tabs.tab-item :active="$filters['type'] == 'all'" href="{{ route('admin.chats.index', ['type' => 'all']) }}" textLabel="{{ __('admin/filter.filters.chats.tabs.all') }}"></x-tabs.tab-item>
			<x-tabs.tab-item :active="$filters['type'] == 'direct'" href="{{ route('admin.chats.index', ['type' => 'direct']) }}" textLabel="{{ __('admin/filter.filters.chats.tabs.direct') }}"></x-tabs.tab-item>
			<x-tabs.tab-item :active="$filters['type'] == 'group'" href="{{ route('admin.chats.index', ['type' => 'group']) }}" textLabel="{{ __('admin/filter.filters.chats.tabs.group') }}"></x-tabs.tab-item>
		</x-tabs.tabs>
	</div>

    <x-table.table>
		<x-slot:filter>
			<div class="mb-4">
				<form action="{{ route('admin.chats.index') }}" method="GET">
					<x-search.searchbar :value="$filters['search']" :cancelUrl="route('admin.chats.index')" />
					<div class="mt-1">
						<x-search.desc description="{{ __('admin/filter.filters.chats.description') }}" />
					</div>
				</form>
			</div>
		</x-slot:filter>
		<x-table.thead>
			<x-table.th>{{ __('table.labels.name') }}</x-table.th>
			<x-table.th>{{ __('table.labels.type') }}</x-table.th>
			<x-table.th>{{ __('table.labels.participants') }}</x-table.th>
			<x-table.th>{{ __('table.labels.messages') }}</x-table.th>
			<x-table.th>{{ __('table.labels.last_activity') }}</x-table.th>
			<x-table.th>#ID</x-table.th>
			<x-table.th>{{ __('labels.table.actions') }}</x-table.th>
		</x-table.thead>
		<x-table.tbody>
			@if($chats->isNotEmpty())
				@foreach ($chats as $chatData)
					@include('admin::chats.index.parts.chat-item', [
						'chatData' => $chatData
					])
				@endforeach
			@else
				<x-table.empty colspan="9"></x-table.empty>
			@endif
		</x-table.tbody>
	</x-table.table>

	@unless($chats->isEmpty())
		<div class="mt-4">
			{{ $chats->onEachSide(1)->withQueryString()->links('pagination.index') }}
		</div>
	@endunless
@endsection
