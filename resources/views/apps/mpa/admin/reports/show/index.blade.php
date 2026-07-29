@extends('adminLayout::index')

@section('pageContent')
    <x-page-title titleText=" {{ __('admin/reports.show_title') }}"></x-page-title>

	<div x-data="alpineComponent">
		<x-sided-content>
			<x-slot:sideContent>
				@if($reportData->type->isPost())
					<x-entity.previews.publication :postData="$reportData->reportable"></x-entity.previews.publication>
				@elseif($reportData->type->isUser())
					<x-entity.previews.user :userData="$reportData->reportable"></x-entity.previews.user>
				@elseif($reportData->type->isGroup())
					<x-entity.previews.group :groupData="$reportData->reportable"></x-entity.previews.group>
				@elseif($reportData->type->isStory())
					<x-entity.previews.story :storyData="$reportData->reportable"></x-entity.previews.story>
				@endif
			</x-slot:sideContent>
			<div class="mb-8">
				<x-entity.header
					avatarUrl="{{ $reportData->reporter->avatar_url }}"
					name="{{ $reportData->reporter->name }}"
				caption="{{ $reportData->reporter->caption }}">

					<x-slot:controlOptions>
						<x-ui.dropdown.dropdown>
							<x-slot:dropdownButton>
								<span class="opacity-50 hover:opacity-100">
									<x-ui.buttons.icon></x-ui.buttons.icon>
								</span>
							</x-slot:dropdownButton>

							<x-ui.dropdown.item tag="a" href="{{ $reportData->reporter->profile_url }}" target="_blank" itemText="{{ __('admin/dd.user.view_profile') }}">
								<x-slot:itemIcon>
									<x-ui-icon type="line" name="user-02"></x-ui-icon>
								</x-slot:itemIcon>
							</x-ui.dropdown.item>
							<x-div></x-div>

							@if($reportData->status->isPending())
								<x-ui.dropdown.item tag="a" x-on:click="processedReport" itemText="{{ __('admin/dd.report.processed') }}">
									<x-slot:itemIcon>
										<x-ui-icon type="line" name="check-circle"></x-ui-icon>
									</x-slot:itemIcon>
								</x-ui.dropdown.item>
								<x-ui.dropdown.item tag="a" x-on:click="ignoreReport" :danger="true" itemText="{{ __('admin/dd.report.ignore') }}">
									<x-slot:itemIcon>
										<x-ui-icon type="solid" name="slash-circle-01"></x-ui-icon>
									</x-slot:itemIcon>
								</x-ui.dropdown.item>
							@endif

							<x-div></x-div>

							<x-ui.dropdown.item tag="a" x-on:click="deleteReport" :danger="true" itemText="{{ __('admin/dd.report.delete') }}">
								<x-slot:itemIcon>
									<x-ui-icon type="line" name="trash-04"></x-ui-icon>
								</x-slot:itemIcon>
							</x-ui.dropdown.item>
						</x-ui.dropdown.dropdown>
					</x-slot:controlOptions>
				</x-entity.header>
			</div>
			<div class="mb-4">
				<x-entity.title title="{{ __('table.labels.reason') }}"></x-entity.title>
			</div>
			<div class="mb-6">
				<div class="p-4 bg-input-pr rounded-2xl">
					<h4 class="text-par-m font-medium mb-1">
						{{ $reportData->reason['title'] }}
					</h4>
					<p class="text-par-s text-lab-sc">
						{{ $reportData->reason['description'] }}
					</p>
				</div>
			</div>
			<div class="mb-4">
				<x-entity.title title="{{ __('admin/reports.about_report') }}"></x-entity.title>
			</div>
			<div class="mb-6">
				<x-line-table.table>
					<x-line-table.row>
						<x-slot:labelText>
							{{ __('table.labels.reported_by') }}
						</x-slot:labelText>
						<x-slot:labelValue>
							<a href="{{ route('admin.users.show', $reportData->reporter->id) }}" target="_blank" class="underline">
								{{ $reportData->reporter->name }}
							</a>
						</x-slot:labelValue>
					</x-line-table.row>
					<x-line-table.row>
						<x-slot:labelText>
							{{ __('table.labels.content') }}
						</x-slot:labelText>
						<x-slot:labelValue>
							{{ $reportData->type->label() }} {{ $reportData->type->emoji() }}
						</x-slot:labelValue>
					</x-line-table.row>
					<x-line-table.row>
						<x-slot:labelText>
							{{ __('table.labels.status') }}
						</x-slot:labelText>
						<x-slot:labelValue>
							{{ $reportData->status->label() }} {{ $reportData->status->emoji() }}
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
						{{ $reportData->id }}
					</x-slot:labelValue>
				</x-striped-table.row>
				<x-striped-table.row>
					<x-slot:labelText>
						{{ __('table.labels.date') }}
					</x-slot:labelText>
					<x-slot:labelValue>
						{{ $reportData->created_at->getFormatted() }}
					</x-slot:labelValue>
				</x-striped-table.row>
			</x-striped-table.table>
		</x-sided-content>
	</div>

	<script>
		window.addEventListener('alpine:init', () => {
			Alpine.data('alpineComponent', () => {
				return {
					deleteReport: () => {
						Alpine.store('confirmModal').open({
							title: "{{ __('admin/prompt.delete_report.title') }}",
							desc: "{{ __('admin/prompt.delete_report.description') }}",
							formAction: "{{ route('admin.reports.delete', $reportData->id) }}"
						});
					},
					processedReport: () => {
						Alpine.store('confirmModal').open({
							title: "{{ __('admin/prompt.processed_report.title') }}",
							desc: "{{ __('admin/prompt.processed_report.description') }}",
							formAction: "{{ route('admin.reports.processed', $reportData->id) }}",
							confirmButtonText: "{{ __('admin/prompt.processed_report.confirm_btn_text') }}"
						});
					},
					ignoreReport: () => {
						Alpine.store('confirmModal').open({
							title: "{{ __('admin/prompt.ignore_report.title') }}",
							desc: "{{ __('admin/prompt.ignore_report.description') }}",
							formAction: "{{ route('admin.reports.ignore', $reportData->id) }}"
						});
					}
				}
			});
		});
	</script>
@endsection
