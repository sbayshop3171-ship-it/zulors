@extends('adminLayout::index')

@section('pageContent')
    <x-page-title titleText=" {{ __('admin/stories.index_title') }}"></x-page-title>

	<x-table.table>
		<x-table.thead>
			<x-table.th>{{ __('table.labels.author') }}</x-table.th>
			<x-table.th>{{ __('table.labels.media') }}</x-table.th>
			<x-table.th>{{ __('table.labels.views') }}</x-table.th>
			<x-table.th>{{ __('table.labels.created_at') }}</x-table.th>
			<x-table.th>{{ __('table.labels.expires_at') }}</x-table.th>
			<x-table.th>#ID</x-table.th>
			<x-table.th>{{ __('labels.table.actions') }}</x-table.th>
		</x-table.thead>
		<x-table.tbody>
			@if($stories->isNotEmpty())
				@foreach ($stories as $storyData)
					@include('admin::stories.index.parts.story-item', [
						'storyData' => $storyData
					])
				@endforeach
			@else
				<x-table.empty colspan="8"></x-table.empty>
			@endif
		</x-table.tbody>
	</x-table.table>

	@unless($stories->isEmpty())
		<div class="mt-4">
			{{ $stories->onEachSide(1)->withQueryString()->links('pagination.index') }}
		</div>
	@endunless
@endsection
