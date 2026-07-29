@extends('adminLayout::index')

@section('pageContent')
	<div x-data="jobsIndex">
		<x-page-title titleText=" {{ __('admin/jobs.index_title') }}"></x-page-title>
		<div class="mb-4">
			<x-tabs.tabs>
				<x-tabs.tab-item :active="$filters['approval'] == 'all'" href="{{ route('admin.jobs.index') }}" textLabel="{{ __('admin/filter.filters.jobs.tabs.all') }}"></x-tabs.tab-item>
				<x-tabs.tab-item :active="$filters['approval'] == 'approved'" href="{{ route('admin.jobs.index', ['approval' => 'approved']) }}" textLabel="{{ __('admin/filter.filters.jobs.tabs.approved') }}"></x-tabs.tab-item>
				<x-tabs.tab-item :active="$filters['approval'] == 'rejected'" href="{{ route('admin.jobs.index', ['approval' => 'rejected']) }}" textLabel="{{ __('admin/filter.filters.jobs.tabs.rejected') }}"></x-tabs.tab-item>
				<x-tabs.tab-item :active="$filters['approval'] == 'pending'" href="{{ route('admin.jobs.index', ['approval' => 'pending']) }}" textLabel="{{ __('admin/filter.filters.jobs.tabs.pending') }}"></x-tabs.tab-item>
			</x-tabs.tabs>
		</div>

		<x-table.table>
			<x-slot:filter>
				<div class="mb-4">
					<form action="{{ route('admin.jobs.index') }}" method="GET">
						<x-search.searchbar :value="$filters['search']" :cancelUrl="route('admin.jobs.index')" />
						<div class="mt-1">
							<x-search.desc description="{{ __('admin/filter.filters.jobs.description') }}" />
						</div>
					</form>
				</div>
			</x-slot:filter>
			<x-table.thead>
				<x-table.th>{{ __('table.labels.author') }}</x-table.th>
				<x-table.th>{{ __('table.labels.title') }}</x-table.th>
				<x-table.th>{{ __('table.labels.category') }}</x-table.th>
				<x-table.th>{{ __('table.labels.approval') }}</x-table.th>
				<x-table.th>{{ __('table.labels.status') }}</x-table.th>
				<x-table.th>{{ __('table.labels.income') }}</x-table.th>
				<x-table.th>{{ __('table.labels.created_at') }}</x-table.th>
				<x-table.th>#ID</x-table.th>
				<x-table.th>{{ __('labels.table.actions') }}</x-table.th>
			</x-table.thead>
			<x-table.tbody>
				@if($jobs->isNotEmpty())
					@foreach ($jobs as $jobData)
						@include('admin::jobs.index.parts.job-item', [
							'jobData' => $jobData
						])
					@endforeach
				@else
					<x-table.empty colspan="9"></x-table.empty>
				@endif
			</x-table.tbody>
		</x-table.table>

		@unless($jobs->isEmpty())
			<div class="mt-4">
				{{ $jobs->onEachSide(1)->withQueryString()->links('pagination.index') }}
			</div>
		@endunless
	</div>

	<script>
		window.addEventListener('alpine:init', () => {
			Alpine.data('jobsIndex', () => ({
				approveJob(formAction) {
					Alpine.store('confirmModal').open({
						title: "{{ __('admin/prompt.approve_job.title') }}",
						desc: "{{ __('admin/prompt.approve_job.description') }}",
						formAction,
						confirmButtonText: "{{ __('admin/prompt.approve_job.confirm_btn_text') }}"
					});
				},
				rejectJob(formAction) {
					Alpine.store('confirmModal').open({
						title: "{{ __('admin/prompt.reject_job.title') }}",
						desc: "{{ __('admin/prompt.reject_job.description') }}",
						formAction,
						confirmButtonText: "{{ __('admin/prompt.reject_job.confirm_btn_text') }}"
					});
				}
			}));
		});
	</script>
@endsection
