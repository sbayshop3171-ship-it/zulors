@extends('adminLayout::index')

@section('pageContent')
<x-page-title titleText=" {{ __('admin/jobs.show_title') }}"></x-page-title>

<div x-data="alpineComponent">
	<x-sided-content>
		<x-slot:sideContent>
			<x-entity.previews.job :jobData="$jobData"></x-entity.previews.job>
		</x-slot:sideContent>

		<div class="mb-8">
			<x-entity.header
				avatarUrl="{{ $jobData->user->avatar_url }}"
				name="{{ $jobData->user->name }}"
			caption="{{ $jobData->user->caption }}">

				<x-slot:controlOptions>
					<x-ui.dropdown.dropdown>
						<x-slot:dropdownButton>
							<span class="opacity-50 hover:opacity-100">
								<x-ui.buttons.icon></x-ui.buttons.icon>
							</span>
						</x-slot:dropdownButton>

						<x-ui.dropdown.item tag="a" href="{{ $jobData->user->profile_url }}" target="_blank" itemText="{{ __('admin/dd.user.view_profile') }}">
							<x-slot:itemIcon>
								<x-ui-icon type="line" name="user-02"></x-ui-icon>
							</x-slot:itemIcon>
						</x-ui.dropdown.item>
						<x-ui.dropdown.item tag="a" href="{{ $jobData->url }}" target="_blank" itemText="{{ __('admin/dd.job.view_job') }}">
							<x-slot:itemIcon>
								<x-ui-icon type="line" name="arrow-up-right"></x-ui-icon>
							</x-slot:itemIcon>
						</x-ui.dropdown.item>

						@if($jobData->approval->isPending())
							<x-ui.dropdown.item x-on:click="approveJob" itemText="{{ __('admin/dd.job.approve') }}">
								<x-slot:itemIcon>
									<x-ui-icon type="line" name="shield-tick"></x-ui-icon>
								</x-slot:itemIcon>
							</x-ui.dropdown.item>
							<x-ui.dropdown.item :danger="true" x-on:click="rejectJob" itemText="{{ __('admin/dd.job.reject') }}">
								<x-slot:itemIcon>
									<x-ui-icon type="solid" name="slash-circle-01"></x-ui-icon>
								</x-slot:itemIcon>
							</x-ui.dropdown.item>
						@endif

						<x-ui.dropdown.item tag="a" :danger="true" x-on:click="deleteJob" itemText="{{ __('admin/dd.job.delete') }}">
							<x-slot:itemIcon>
								<x-ui-icon type="line" name="trash-04"></x-ui-icon>
							</x-slot:itemIcon>
						</x-ui.dropdown.item>
					</x-ui.dropdown.dropdown>
				</x-slot:controlOptions>
			</x-entity.header>
		</div>
		@if($jobData->approval->isPending())
			<div class="mb-6">
				<div class="p-4 bg-input-pr rounded-2xl">
					<div class="mb-4">
						<h4 class="text-par-m font-medium mb-1">
							{{ $jobData->approval->label() }} {{ $jobData->approval->emoji() }}
						</h4>
						<p class="text-par-n text-lab-sc">
							{{ __('admin/prompt.approve_job.description') }}
						</p>
					</div>
					<div class="flex gap-2">
						<x-ui.buttons.pill x-on:click="approveJob" size="sm" btnText="{{ __('admin/dd.job.approve') }}"></x-ui.buttons.pill>
						<x-ui.buttons.pill x-on:click="rejectJob" variant="danger" size="sm" btnText="{{ __('admin/dd.job.reject') }}"></x-ui.buttons.pill>
					</div>
				</div>
			</div>
		@endif
		<div class="mb-4">
			<x-entity.title title="{{ __('admin/jobs.about_job') }}" caption="{{ $jobData->created_at->getFormatted() }}"></x-entity.title>
		</div>
		<div class="mb-6">
			<x-counter.counter>
				<x-counter.counter-item counterValue="{{ $jobData->formatted_income }}" captionText="{{ __('admin/jobs.income') }}"></x-counter.counter-item>
				<x-counter.counter-item counterValue="{{ $jobData->views_count }}" captionText="{{ __('labels.views') }}"></x-counter.counter-item>
				<x-counter.counter-item counterValue="{{ $jobData->applications_count }}" captionText="{{ __('admin/jobs.applications_count') }}"></x-counter.counter-item>
			</x-counter.counter>
		</div>
		<div class="mb-6">
			<x-line-table.table>
				<x-line-table.row>
					<x-slot:labelText>
						{{ __('table.labels.author') }}
					</x-slot:labelText>
					<x-slot:labelValue>
						<a href="{{ route('admin.users.show', $jobData->user->id) }}" target="_blank" class="underline">
							{{ $jobData->user->name }}
						</a>
					</x-slot:labelValue>
				</x-line-table.row>

				<x-line-table.row>
					<x-slot:labelText>
						{{ __('table.labels.income') }}
					</x-slot:labelText>
					<x-slot:labelValue>
						@if($jobData->is_start_income)
							{{ __('labels.income_from', ['amount' => $jobData->formatted_income]) }}
						@else
							{{ __('labels.income_to', ['amount' => $jobData->formatted_income]) }}
						@endif
					</x-slot:labelValue>
				</x-line-table.row>
				<x-line-table.row>
					<x-slot:labelText>
						{{ __('table.labels.category') }}
					</x-slot:labelText>
					<x-slot:labelValue>
						{{ $jobData->category_name }}
					</x-slot:labelValue>
				</x-line-table.row>
				<x-line-table.row>
					<x-slot:labelText>
						{{ __('table.labels.approval') }}
					</x-slot:labelText>
					<x-slot:labelValue>
						<x-badge variant="{{ $jobData->approval->badgeVariant() }}">
							{{ $jobData->approval->label() }} {{ $jobData->approval->emoji() }}
						</x-badge>
					</x-slot:labelValue>
				</x-line-table.row>
				<x-line-table.row>
					<x-slot:labelText>
						{{ __('table.labels.status') }}
					</x-slot:labelText>
					<x-slot:labelValue>
						<x-badge variant="{{ $jobData->status->badgeVariant() }}">
							{{ $jobData->status->label() }} {{ $jobData->status->emoji() }}
						</x-badge>
					</x-slot:labelValue>
				</x-line-table.row>
				<x-line-table.row>
					<x-slot:labelText>
						{{ __('table.labels.job_type') }}
					</x-slot:labelText>
					<x-slot:labelValue>
						{{ $jobData->type->label() }}
					</x-slot:labelValue>
				</x-line-table.row>
				<x-line-table.row>
					<x-slot:labelText>
						{{ __('table.labels.urgency') }}
					</x-slot:labelText>
					<x-slot:labelValue>
						{{ empty($jobData->is_urgent) ? __('labels.no') : __('labels.yes') }}
					</x-slot:labelValue>
				</x-line-table.row>
				<x-line-table.row>
					<x-slot:labelText>
						{{ __('table.labels.bookmarks') }}
					</x-slot:labelText>
					<x-slot:labelValue>
						{{ $jobData->bookmarks_count }}
					</x-slot:labelValue>
				</x-line-table.row>
			</x-line-table.table>
		</div>
		<div class="mb-4">
			<x-entity.title title="{{ __('labels.additional_info') }}"></x-entity.title>
		</div>

		<x-striped-table.table>
			<x-striped-table.row>
				<x-slot:labelText>
					#ID
				</x-slot:labelText>
				<x-slot:labelValue>
					{{ $jobData->id }}
				</x-slot:labelValue>
			</x-striped-table.row>
			<x-striped-table.row>
				<x-slot:labelText>
					Hash ID
				</x-slot:labelText>
				<x-slot:labelValue>
					{{ $jobData->hashId }}
				</x-slot:labelValue>
			</x-striped-table.row>
			<x-striped-table.row>
				<x-slot:labelText>
					{{ __('table.labels.created_at') }}
				</x-slot:labelText>
				<x-slot:labelValue>
					{{ $jobData->created_at->getFormatted() }}
				</x-slot:labelValue>
			</x-striped-table.row>

			<x-striped-table.row>
				<x-slot:labelText>
					{{ __('table.labels.last_contacted_at') }}
				</x-slot:labelText>
				<x-slot:labelValue>
					@if($jobData->last_contacted_at)
						{{ $jobData->last_contacted_at->getFormatted() }}
					@else
						{{ __('labels.never') }}
					@endif
				</x-slot:labelValue>
			</x-striped-table.row>
		</x-striped-table.table>
	</x-sided-content>
</div>
<script>
	window.addEventListener('alpine:init', () => {
		Alpine.data('alpineComponent', () => {
			return {
				deleteJob: () => {
					Alpine.store('confirmModal').open({
						title: "{{ __('admin/prompt.delete_job.title') }}",
						desc: "{{ __('admin/prompt.delete_job.description') }}",
						formAction: "{{ route('admin.jobs.destroy', $jobData->id) }}"
					});
				},
				approveJob: () => {
					Alpine.store('confirmModal').open({
						title: "{{ __('admin/prompt.approve_job.title') }}",
						desc: "{{ __('admin/prompt.approve_job.description') }}",
						formAction: "{{ route('admin.jobs.approve', $jobData->id) }}",
						confirmButtonText: "{{ __('admin/prompt.approve_job.confirm_btn_text') }}"
					});
				},
				rejectJob: () => {
					Alpine.store('confirmModal').open({
						title: "{{ __('admin/prompt.reject_job.title') }}",
						desc: "{{ __('admin/prompt.reject_job.description') }}",
						formAction: "{{ route('admin.jobs.reject', $jobData->id) }}",
						confirmButtonText: "{{ __('admin/prompt.reject_job.confirm_btn_text') }}"
					});
				}
			}
		});
	});
</script>
@endsection
