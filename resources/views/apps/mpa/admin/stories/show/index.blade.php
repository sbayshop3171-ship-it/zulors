@extends('adminLayout::index')

@section('pageContent')
<div x-data="alpineComponent">
    <x-page-title titleText=" {{ __('admin/stories.show_title') }}"></x-page-title>

	<x-sided-content>
		<x-slot:sideContent>
			<x-entity.previews.story :storyData="$storyData"></x-entity.previews.story>
		</x-slot:sideContent>

		<div class="mb-8">
			<x-entity.header
				avatarUrl="{{ $storyData->story->user->avatar_url }}"
				name="{{ $storyData->story->user->name }}"
			caption="{{ $storyData->story->user->caption }}">
				<x-slot:controlOptions>
					<x-ui.dropdown.dropdown>
						<x-slot:dropdownButton>
							<span class="opacity-50 hover:opacity-100">
								<x-ui.buttons.icon></x-ui.buttons.icon>
							</span>
						</x-slot:dropdownButton>

						@unless($storyData->isExpired())
							<x-ui.dropdown.item tag="a" href="{{ $storyData->story->url }}" target="_blank" itemText="{{ __('admin/dd.story.view_story') }}">
								<x-slot:itemIcon>
									<x-ui-icon type="line" name="arrow-up-right"></x-ui-icon>
								</x-slot:itemIcon>
							</x-ui.dropdown.item>
						@endif

						<x-ui.dropdown.item tag="a" href="{{ $storyData->story->user->profile_url }}" target="_blank" itemText="{{ __('admin/dd.user.view_profile') }}">
							<x-slot:itemIcon>
								<x-ui-icon type="line" name="user-02"></x-ui-icon>
							</x-slot:itemIcon>
						</x-ui.dropdown.item>
						<x-ui.dropdown.item :danger="true" x-on:click="deleteStory" itemText="{{ __('admin/dd.story.delete') }}">
							<x-slot:itemIcon>
								<x-ui-icon type="line" name="trash-04"></x-ui-icon>
							</x-slot:itemIcon>
						</x-ui.dropdown.item>
					</x-ui.dropdown.dropdown>
				</x-slot:controlOptions>
			</x-entity.header>
		</div>

		<div class="mb-4">
			<x-entity.title title="{{ __('admin/stories.about_story') }}" caption="{{ $storyData->created_at->getFormatted() }}"></x-entity.title>
		</div>
		<div class="mb-6">
			<x-counter.counter>
				<x-counter.counter-item counterValue="{{ $storyData->views_count }}" captionText="{{ __('labels.views') }}"></x-counter.counter-item>
				<x-counter.counter-item counterValue="{{ $storyData->duration_seconds }}" captionText="{{ __('table.labels.duration') }}"></x-counter.counter-item>
				<x-counter.counter-item counterValue="{{ $storyData->expires_at->getRemainingHours() }}" captionText="{{ __('table.labels.remaining_hours') }}"></x-counter.counter-item>
			</x-counter.counter>
		</div>

		<div class="mb-6">
			<x-line-table.table>
				<x-line-table.row>
					<x-slot:labelText>
						{{ __('table.labels.author') }}
					</x-slot:labelText>
					<x-slot:labelValue>
						<a href="{{ route('admin.users.show', $storyData->story->user->id) }}" target="_blank" class="underline">
							{{ $storyData->story->user->name }}
						</a>
					</x-slot:labelValue>
				</x-line-table.row>
				<x-line-table.row>
					<x-slot:labelText>
						{{ __('table.labels.type') }}
					</x-slot:labelText>
					<x-slot:labelValue>
						{{ $storyData->type->label() }}
					</x-slot:labelValue>
				</x-line-table.row>
				<x-line-table.row>
					<x-slot:labelText>
						{{ __('table.labels.duration') }}
					</x-slot:labelText>
					<x-slot:labelValue>
						{{ $storyData->duration_seconds }}
					</x-slot:labelValue>
				</x-line-table.row>
				<x-line-table.row>
					<x-slot:labelText>
						{{ __('table.labels.expires_at') }}
					</x-slot:labelText>
					<x-slot:labelValue>
						{{ $storyData->expires_at->getFormatted() }}
					</x-slot:labelValue>
				</x-line-table.row>
				<x-line-table.row>
					<x-slot:labelText>
						{{ __('table.labels.is_highlight') }}
					</x-slot:labelText>
					<x-slot:labelValue>
						{{ $storyData->is_highlight ? '✅' : '❌' }}
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
					{{ $storyData->id }}
				</x-slot:labelValue>
			</x-striped-table.row>
			<x-striped-table.row>
				<x-slot:labelText>
					{{ __('table.labels.created_at') }}
				</x-slot:labelText>
				<x-slot:labelValue>
					{{ $storyData->created_at->getFormatted() }}
				</x-slot:labelValue>
			</x-striped-table.row>
		</x-striped-table.table>
	</x-sided-content>
</div>

<script>
	window.addEventListener('alpine:init', () => {
		Alpine.data('alpineComponent', () => {
			return {
				deleteStory: () => {
					Alpine.store('confirmModal').open({
						title: "{{ __('admin/prompt.delete_story.title') }}",
						desc: "{{ __('admin/prompt.delete_story.description') }}",
						formAction: "{{ route('admin.stories.destroy', $storyData->id) }}"
					});
				}
			}
		});
	});
</script>

@endsection
