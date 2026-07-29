@extends('adminLayout::index')

@section('pageContent')
    <x-page-title titleText=" {{ __('admin/users.index_title') }}"></x-page-title>
	<div class="mb-4">
		<x-tabs.tabs>
			<x-tabs.tab-item :active="$filters['type'] == 'all'" href="{{ route('admin.users.index') }}" textLabel="{{ __('admin/filter.filters.users.tabs.all') }}"></x-tabs.tab-item>
			<x-tabs.tab-item :active="$filters['type'] == 'author'" href="{{ route('admin.users.index', ['type' => 'author']) }}" textLabel="{{ __('admin/filter.filters.users.tabs.authors') }}"></x-tabs.tab-item>
			<x-tabs.tab-item :active="$filters['type'] == 'reader'" href="{{ route('admin.users.index', ['type' => 'reader']) }}" textLabel="{{ __('admin/filter.filters.users.tabs.readers') }}"></x-tabs.tab-item>
		</x-tabs.tabs>
	</div>

	<x-table.table>
		<x-slot:filter>
			<div class="mb-4">
				<form action="{{ route('admin.users.index') }}" method="GET">
					<x-search.searchbar :value="$filters['search']" :cancelUrl="route('admin.users.index')" />
					<div class="mt-1">
						<x-search.desc description="{{ __('admin/filter.filters.users.description') }}" />
					</div>
				</form>
			</div>
		</x-slot:filter>
		<x-table.thead>
			<x-table.th>{{ __('table.labels.full_name') }}</x-table.th>
			<x-table.th>{{ __('table.labels.email') }}</x-table.th>
			<x-table.th>{{ __('table.labels.type') }}</x-table.th>
			<x-table.th>{{ __('table.labels.country') }}</x-table.th>
			<x-table.th>IP</x-table.th>
			<x-table.th>{{ __('table.labels.joined_at') }}</x-table.th>
			<x-table.th>{{ __('table.labels.last_seen') }}</x-table.th>
			<x-table.th>#ID</x-table.th>
		</x-table.thead>
		<x-table.tbody>
			@if($users->isNotEmpty())
				@foreach ($users as $userData)
					@include('admin::users.index.parts.user-item', [
						'userData' => $userData,
					])
				@endforeach
			@else
				<x-table.empty colspan="8"></x-table.empty>
			@endif
		</x-table.tbody>
	</x-table.table>

	@unless($users->isEmpty())
		<div class="mt-4">
			{{ $users->onEachSide(1)->withQueryString()->links('pagination.index') }}
		</div>
	@endunless
@endsection
