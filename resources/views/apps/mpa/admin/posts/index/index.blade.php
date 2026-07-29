@extends('adminLayout::index')

@section('pageContent')
    <x-page-title titleText=" {{ __('admin/posts.index_title') }}"></x-page-title>

	<x-table.table>
		<x-table.thead>
			<x-table.th>{{ __('table.labels.author') }}</x-table.th>
			<x-table.th>{{ __('table.labels.content') }}</x-table.th>
			<x-table.th>{{ __('table.labels.type') }}</x-table.th>
			<x-table.th>{{ __('table.labels.comments') }}</x-table.th>
			<x-table.th>{{ __('table.labels.views') }}</x-table.th>
			<x-table.th>{{ __('table.labels.created_at') }}</x-table.th>
			<x-table.th>#ID</x-table.th>
			<x-table.th>{{ __('labels.table.actions') }}</x-table.th>
		</x-table.thead>
		<x-table.tbody>
			@if($posts->isNotEmpty())
				@foreach ($posts as $postData)
					@include('admin::posts.index.parts.post-item', [
						'postData' => $postData
					])
				@endforeach
			@else
				<x-table.empty colspan="9"></x-table.empty>
			@endif
		</x-table.tbody>
	</x-table.table>

	@unless($posts->isEmpty())
		<div class="mt-4">
			{{ $posts->onEachSide(1)->withQueryString()->links('pagination.index') }}
		</div>
	@endunless
@endsection
